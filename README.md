# ⏱️ Mikro Mola — Esneme & Su Takibi

Bilgisayar başında oturanlar ve unutkanlar için mikro-mola uygulaması.
Ayarlanabilir sürede **esneme molaları** (boyn, omuz, bilek, gövde hareketleri — animasyonlu),
eğlenceli bir **su içme takibi** (dokundukça dolan şişe 🍶), **koyu/açık mod**, **hafif sesli uyarılar**,
**arka planda çalışan sayaç** ve **konfetili hedef kutlamaları**.

## Çalıştırma

```bash
pip install -r requirements.txt
uvicorn main:app --reload --port 8000
```

Ardından tarayıcıda aç: **http://127.0.0.1:8000**

## Özellikler

- **Mola sayacı** — 15 / 25 / 45 / 60 dk seçilebilir (varsayılan 45 dk). Süre dolunca hafif çan
  sesi + masaüstü bildirimi + ekran içi uyarı gelir ve animasyonlu esneme rutini açılır:
  20 hareketlik havuzdan (10'u ayakta, 10'u sandalyede oturarak) her molada rastgele 5 hareket
  gelir — içi dolgulu insan silüeti hareketi eklem eklem canlandırır. Bitince sayaç yeniden başlar.
- **Arka plan sayacı** — sayaç zaman damgasıyla çalışır: sekme arka plana alınsa ya da sayfa
  kapatılıp açılsa bile kalan süre doğru hesaplanır; süre geçmişse dönüşte mola hatırlatılır.
- **Su takibi** — bardağa/şişeye dokun, şişe dolsun 🌊. Günlük hedef (1.5–3 L) ve bardak boyutu
  (200 ml / 400 ml / 0.5 L) kişiseldir. Saatlik hatırlatıcı açılıp kapatılabilir.
- **Geçmiş** — son 7 günün grafiği, hedef çizgisi ve gün serisi (streak 🔥).
- **Tema** — sağ üstten koyu/açık mod; tercih hatırlanır.
- **Veri** — her şey yalnızca tarayıcının LocalStorage'ında tutulur, sunucuya hiçbir şey gitmez.
- **Kutlama** — hedef tamamlanınca konfeti 🎉 (canvas-confetti).

## Teknolojiler

HTML5 · CSS · Vanilla JS · FastAPI (statik sunum + sağlık uçları) · Lucide Icons · canvas-confetti · LocalStorage

## Not

Sesler Web Audio API ile üretilir (dosya gerektirmez) ve bilinçli olarak düşük seviyededir.
Ses, tarayıcı politikası gereği ilk tıklamadan sonra aktif olur.
