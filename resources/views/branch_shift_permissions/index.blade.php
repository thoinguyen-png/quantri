<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-4 p-4 sm:p-6">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h1 class="text-xl font-black text-slate-900">Ca được phép theo cơ sở</h1>
            <p class="mt-1 text-sm text-slate-600">Chọn các ca từng cơ sở được dùng khi tắt công tắc hiển thị toàn bộ ca trong phần Cấu hình.</p>
            <a href="{{ route('settings.edit') }}" class="mt-3 inline-flex text-sm font-bold text-blue-700 hover:text-blue-800">Mở Cấu hình hệ thống</a>
        </section>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-700">{{ session('success') }}</div>
        @endif

        @foreach ($branches as $branch)
            @php($allowedIds = $branch->shifts->pluck('id')->map(fn ($id) => (string) $id)->all())
            <form method="POST" action="{{ route('branch-shift-permissions.update', $branch) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                @csrf
                @method('PUT')
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-black text-slate-900">{{ $branch->name }}</h2>
                        <p class="text-sm text-slate-500">{{ $branch->address ?: 'Chưa có địa chỉ' }}</p>
                    </div>
                    <button class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white">Lưu ca cho cơ sở</button>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($shifts as $shift)
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700">
                            <input type="checkbox" name="shift_ids[]" value="{{ $shift->id }}" class="rounded border-slate-300 text-blue-600" @checked(in_array((string) $shift->id, $allowedIds, true))>
                            <span><strong class="block text-slate-900">{{ $shift->name }}</strong>{{ $shift->start_at->format('H:i') }} - {{ $shift->end_at->format('H:i') }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-slate-500">Chưa có ca làm để cấu hình.</p>
                    @endforelse
                </div>
            </form>
        @endforeach
    </div>
</x-app-layout>
