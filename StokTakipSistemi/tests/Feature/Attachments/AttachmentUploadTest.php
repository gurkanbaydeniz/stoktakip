<?php

namespace Tests\Feature\Attachments;

use App\Models\Attachment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private string $adminToken;

    private string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $company = Company::create(['name' => 'Kahve Zinciri']);
        $this->admin = User::create([
            'company_id' => $company->id,
            'name' => 'Patron',
            'email' => 'patron@test.com',
            'password' => 'Patron1234',
            'role' => User::ROLE_ADMIN,
        ]);
        $this->staff = User::create([
            'company_id' => $company->id,
            'name' => 'Barista',
            'email' => 'barista@test.com',
            'password' => 'Barista123',
            'role' => User::ROLE_STAFF,
        ]);

        $this->adminToken = $this->admin->createToken('t')->plainTextToken;
        $this->staffToken = $this->staff->createToken('t')->plainTextToken;
    }

    private function batch(): int
    {
        $product = $this->admin->company->products()->create(['name' => 'Süt 1L']);
        $batch = $this->admin->company->batches()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'expiry_date' => '2027-01-15',
        ]);
        $batch->stockMovements()->create([
            'company_id' => $this->admin->company_id,
            'product_id' => $product->id,
            'user_id' => $this->admin->id,
            'type' => 'in',
            'quantity' => 10,
        ]);

        return $batch->id;
    }

    public function test_admin_irsaliye_fotografi_yukler(): void
    {
        $response = $this->withSanctumToken($this->adminToken)
            ->post('/api/v1/attachments', [
                'file' => UploadedFile::fake()->image('irsaliye.jpg', 800, 600),
                'kind' => 'waybill',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.kind', 'waybill')
            ->assertJsonPath('data.mime_type', 'image/jpeg')
            ->assertJsonPath('data.original_name', 'irsaliye.jpg')
            ->assertJsonPath('data.ocr_status', Attachment::OCR_PENDING)
            ->assertJsonPath('data.uploader.email', 'patron@test.com');

        $attachment = Attachment::first();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame($this->admin->company_id, $attachment->company_id);
        // Şirket bazlı klasör yapısı
        $this->assertStringStartsWith(
            "attachments/{$this->admin->company_id}/",
            $attachment->path
        );
    }

    public function test_staff_yukleme_yapamaz_403(): void
    {
        $this->withSanctumToken($this->staffToken)
            ->post('/api/v1/attachments', [
                'file' => UploadedFile::fake()->image('x.jpg'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_ek_partiye_baglanir(): void
    {
        $batchId = $this->batch();

        $this->withSanctumToken($this->adminToken)
            ->post('/api/v1/attachments', [
                'file' => UploadedFile::fake()->image('lot.jpg'),
                'batch_id' => $batchId,
            ])
            ->assertCreated()
            ->assertJsonPath('data.batch_id', $batchId);

        $attachment = Attachment::first();
        $this->assertSame($batchId, $attachment->attachable_id);
    }

    public function test_baska_sirketin_partisine_baglanamaz_404(): void
    {
        $otherCompany = Company::create(['name' => 'Rakip']);
        $otherProduct = $otherCompany->products()->create(['name' => 'Rakip Sütü']);
        $otherBatch = $otherCompany->batches()->create([
            'product_id' => $otherProduct->id,
            'quantity' => 5,
        ]);

        $this->withSanctumToken($this->adminToken)
            ->post('/api/v1/attachments', [
                'file' => UploadedFile::fake()->image('x.jpg'),
                'batch_id' => $otherBatch->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_gecersiz_dosya_turu_422(): void
    {
        $this->withSanctumToken($this->adminToken)
            ->post('/api/v1/attachments', [
                'file' => UploadedFile::fake()->create('belge.txt', 10, 'text/plain'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_liste_ve_filtreleme(): void
    {
        $batchId = $this->batch();

        $this->withSanctumToken($this->adminToken)
            ->post('/api/v1/attachments', ['file' => UploadedFile::fake()->image('a.jpg')]);
        $this->withSanctumToken($this->adminToken)
            ->post('/api/v1/attachments', [
                'file' => UploadedFile::fake()->image('b.jpg'),
                'batch_id' => $batchId,
                'kind' => 'product_photo',
            ]);

        $this->withSanctumToken($this->staffToken)
            ->getJson('/api/v1/attachments')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withSanctumToken($this->staffToken)
            ->getJson("/api/v1/attachments?batch_id={$batchId}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'product_photo');
    }

    public function test_indirme_kimlik_dogrulamali_ve_icerik_dogru(): void
    {
        $this->withSanctumToken($this->adminToken)
            ->post('/api/v1/attachments', [
                'file' => UploadedFile::fake()->image('irsaliye.jpg'),
            ]);
        $attachment = Attachment::first();

        $this->withSanctumToken($this->staffToken)
            ->get("/api/v1/attachments/{$attachment->id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        // Token'sız erişim engellenmeli
        $this->asGuest()
            ->getJson("/api/v1/attachments/{$attachment->id}/download")
            ->assertUnauthorized();
    }

    public function test_baska_sirket_eki_goremez_404(): void
    {
        $this->withSanctumToken($this->adminToken)
            ->post('/api/v1/attachments', ['file' => UploadedFile::fake()->image('a.jpg')]);
        $attachment = Attachment::first();

        $otherCompany = Company::create(['name' => 'Rakip']);
        $other = User::create([
            'company_id' => $otherCompany->id,
            'name' => 'Rakip',
            'email' => 'rakip@test.com',
            'password' => 'Rakip1234',
            'role' => User::ROLE_ADMIN,
        ]);
        $otherToken = $other->createToken('t')->plainTextToken;

        $this->withSanctumToken($otherToken)
            ->getJson("/api/v1/attachments/{$attachment->id}")
            ->assertNotFound();

        $this->withSanctumToken($otherToken)
            ->get("/api/v1/attachments/{$attachment->id}/download")
            ->assertNotFound();
    }

    public function test_admin_eki_siler_dosya_da_kalkar(): void
    {
        $this->withSanctumToken($this->adminToken)
            ->post('/api/v1/attachments', ['file' => UploadedFile::fake()->image('a.jpg')]);
        $attachment = Attachment::first();

        $this->withSanctumToken($this->adminToken)
            ->deleteJson("/api/v1/attachments/{$attachment->id}")
            ->assertOk();

        $this->assertModelMissing($attachment);
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_staff_eki_silemez_403(): void
    {
        $this->withSanctumToken($this->adminToken)
            ->post('/api/v1/attachments', ['file' => UploadedFile::fake()->image('a.jpg')]);
        $attachment = Attachment::first();

        $this->withSanctumToken($this->staffToken)
            ->deleteJson("/api/v1/attachments/{$attachment->id}")
            ->assertForbidden();
    }
}
