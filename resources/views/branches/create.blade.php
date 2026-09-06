<x-app-layout>
    <div
        class="mx-auto max-w-4xl space-y-6 p-4 sm:p-6 lg:p-8"
        x-data="{
            previewUrl: null,
            isLocating: false,
            geoError: null,
            getCurrentLocation() {
                if (!navigator.geolocation) {
                    this.geoError = 'Trình duyệt không hỗ trợ định vị GPS.';
                    return;
                }
                this.isLocating = true;
                this.geoError = null;
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        this.isLocating = false;
                        document.getElementById('latitude').value = pos.coords.latitude.toFixed(6);
                        document.getElementById('longitude').value = pos.coords.longitude.toFixed(6);
                    },
                    (err) => {
                        this.isLocating = false;
                        this.geoError = 'Không thể lấy vị trí GPS: ' + (err.message || 'Vui lòng cho phép quyền truy cập vị trí.');
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            }
        }"
    >
        <!-- Header -->
        <section class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <a href="{{ route('branches.index') }}" class="inline-flex items-center gap-1.5 text-xs font-black text-indigo-600 hover:text-indigo-800 transition">
                        &larr; Quay lại danh sách cơ sở
                    </a>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">
                        Thêm Cơ Sở (Chi Nhánh) Mới
                    </h1>
                    <p class="mt-1 text-sm font-medium text-slate-600">
                        Nhập tên, số hotline, địa chỉ, định vị GPS chấm công và logo in thẻ nhân sự.
                    </p>
                </div>
            </div>
        </section>

        <!-- Form tạo mới -->
        <form method="POST" action="{{ route('branches.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- Card 1: Thông tin chung -->
            <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-black text-slate-950">1. Thông tin chung</h2>
                        <p class="text-xs font-medium text-slate-500">Tên thương hiệu chi nhánh, số hotline liên hệ và địa chỉ hoạt động.</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <!-- Tên cơ sở -->
                    <div class="sm:col-span-2">
                        <label for="name" class="block text-xs font-black uppercase text-slate-700">
                            Tên cơ sở / Chi nhánh <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            required
                            placeholder="Ví dụ: SANTORI KTV, KARAOKE PARIS BY NIGHT, SÀI GÒN PHỐ..."
                            class="mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50/60 px-4 py-3 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 transition"
                        >
                        @error('name')
                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Hotline chi nhánh -->
                    <div>
                        <label for="hotline" class="block text-xs font-black uppercase text-slate-700">
                            Số Hotline chi nhánh (In lên thẻ & liên hệ)
                        </label>
                        <div class="relative mt-2">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-amber-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </div>
                            <input
                                type="text"
                                id="hotline"
                                name="hotline"
                                value="{{ old('hotline') }}"
                                placeholder="Ví dụ: 0909 123 456"
                                class="block w-full rounded-2xl border border-slate-200 bg-slate-50/60 py-3 pl-10 pr-4 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 transition"
                            >
                        </div>
                        @error('hotline')
                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Địa chỉ -->
                    <div class="sm:col-span-2">
                        <label for="address" class="block text-xs font-black uppercase text-slate-700">
                            Địa chỉ chi tiết
                        </label>
                        <input
                            type="text"
                            id="address"
                            name="address"
                            value="{{ old('address') }}"
                            placeholder="Ví dụ: 123 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh"
                            class="mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50/60 px-4 py-3 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 transition"
                        >
                        @error('address')
                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Card 2: Định vị GPS -->
            <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-black text-slate-950">2. Cấu hình định vị GPS chấm công</h2>
                            <p class="text-xs font-medium text-slate-500">Giới hạn khoảng cách nhân viên quét mã chấm công tại chi nhánh.</p>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="getCurrentLocation()"
                        :disabled="isLocating"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3.5 py-2 text-xs font-black text-indigo-700 hover:bg-indigo-100 transition shadow-sm"
                    >
                        <svg x-show="!isLocating" class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <svg x-show="isLocating" class="h-4 w-4 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="isLocating ? 'Đang lấy vị trí...' : 'Lấy GPS vị trí hiện tại'"></span>
                    </button>
                </div>

                <div x-show="geoError" class="mt-4 rounded-xl bg-rose-50 p-3 text-xs font-bold text-rose-700" x-text="geoError"></div>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="latitude" class="block text-xs font-black uppercase text-slate-700">
                            Vĩ độ (Latitude)
                        </label>
                        <input
                            type="number"
                            step="any"
                            id="latitude"
                            name="latitude"
                            value="{{ old('latitude') }}"
                            placeholder="10.776889"
                            class="mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50/60 px-4 py-3 text-sm font-mono font-semibold text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 transition"
                        >
                        @error('latitude')
                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="longitude" class="block text-xs font-black uppercase text-slate-700">
                            Kinh độ (Longitude)
                        </label>
                        <input
                            type="number"
                            step="any"
                            id="longitude"
                            name="longitude"
                            value="{{ old('longitude') }}"
                            placeholder="106.700806"
                            class="mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50/60 px-4 py-3 text-sm font-mono font-semibold text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 transition"
                        >
                        @error('longitude')
                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="gps_radius" class="block text-xs font-black uppercase text-slate-700">
                            Bán kính hợp lệ (mét)
                        </label>
                        <input
                            type="number"
                            id="gps_radius"
                            name="gps_radius"
                            value="{{ old('gps_radius', 100) }}"
                            min="10"
                            max="5000"
                            class="mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50/60 px-4 py-3 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 transition"
                        >
                        @error('gps_radius')
                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Card 3: Logo thẻ nhân sự -->
            <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-purple-50 text-purple-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-black text-slate-950">3. Logo in thẻ nhân sự chi nhánh</h2>
                        <p class="text-xs font-medium text-slate-500">Logo riêng sẽ được in trên thẻ đeo ngực và bảng đánh giá A5 của nhân sự thuộc cơ sở này.</p>
                    </div>
                </div>

                <div class="mt-6 flex flex-col gap-6 sm:flex-row sm:items-center">
                    <!-- Preview Box -->
                    <div class="flex flex-col items-center gap-2">
                        <div class="relative flex h-24 w-40 items-center justify-center overflow-hidden rounded-2xl bg-slate-950 p-3 shadow-2xl ring-1 ring-slate-800">
                            <template x-if="previewUrl">
                                <img :src="previewUrl" alt="Logo preview" class="max-h-full max-w-full object-contain">
                            </template>
                            <template x-if="!previewUrl">
                                <img src="{{ asset('icons/logoSantori.png') }}" alt="Logo mặc định" class="max-h-full max-w-full object-contain">
                            </template>
                        </div>
                        <span class="text-[11px] font-bold text-slate-400" x-text="previewUrl ? 'Xem trước ảnh tải lên' : 'Logo mặc định Santori'"></span>
                    </div>

                    <!-- Upload Input -->
                    <div class="flex-1 min-w-0">
                        <label for="staff_card_logo" class="block text-xs font-black uppercase text-slate-700">
                            Chọn tệp ảnh logo (PNG, JPG, JPEG, WebP — tối đa 10 MB)
                        </label>
                        <p class="mt-1 text-xs text-slate-500">
                            Khuyến nghị sử dụng ảnh nền trong suốt (PNG/WebP) màu vàng ánh kim hoặc màu sáng để nổi bật trên nền thẻ đen.
                        </p>
                        <input
                            id="staff_card_logo"
                            name="staff_card_logo"
                            type="file"
                            accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                            @change="const file = $event.target.files[0]; if (file) previewUrl = URL.createObjectURL(file)"
                            class="mt-3 block w-full rounded-2xl border border-slate-200 bg-slate-50/60 p-2 text-sm text-slate-700 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2.5 file:text-xs file:font-black file:text-white hover:file:bg-indigo-600 transition"
                        >
                        @error('staff_card_logo')
                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Card 4: Trạng thái cơ sở -->
            <div class="rounded-[2rem] border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-black text-slate-950">Trạng thái hoạt động</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Kích hoạt để cơ sở sẵn sàng chấm công, phân ca và xuất thẻ.</p>
                    </div>

                    <label class="relative inline-flex cursor-pointer items-center">
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            {{ old('is_active', true) ? 'checked' : '' }}
                            class="peer sr-only"
                        >
                        <div class="peer h-7 w-14 rounded-full bg-slate-200 after:absolute after:left-[4px] after:top-[4px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-emerald-500 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none"></div>
                    </label>
                </div>
            </div>

            <!-- Nút bấm submit -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <a
                    href="{{ route('branches.index') }}"
                    class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white px-6 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50"
                >
                    Hủy bỏ
                </a>
                <button
                    type="submit"
                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-600 to-blue-600 px-8 text-sm font-black text-white shadow-lg shadow-indigo-200 transition hover:-translate-y-0.5 hover:shadow-indigo-300"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    Lưu Cơ Sở Mới
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
