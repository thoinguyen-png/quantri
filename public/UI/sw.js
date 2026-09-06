const CACHE = "maxsim-ui-separated-v1";
const ASSETS = ["./00_danh_sach_ui.html", "./01_form_danh_gia_khach.html", "./02_popup_thong_bao_noi_bo.html", "./03_thong_ke_nhan_su.html", "./04_thong_ke_quan_ly.html", "./05_thong_ke_admin.html", "./manifest.webmanifest"];
self.addEventListener("install", (event) => event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(ASSETS))));
self.addEventListener("activate", (event) => event.waitUntil(self.clients.claim()));
self.addEventListener("fetch", (event) => {
  if(event.request.method !== "GET") return;
  event.respondWith(caches.match(event.request).then((cached) => cached || fetch(event.request)));
});
