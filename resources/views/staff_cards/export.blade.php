<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
        <section class="rounded-[2rem] bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-indigo-600">Thẻ nhân sự</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">Xuất thẻ nhân sự</h1>
                    <p class="mt-2 text-sm text-slate-500">Chọn cơ sở, phạm vi nhân sự và mẫu thẻ trước khi xem lại danh sách.</p>
                </div>
                <a href="{{ route('users.index') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Quay lại nhân sự</a>
            </div>
        </section>

        @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if (auth()->user()->role === 'admin' && $branches->isNotEmpty())
        <form method="GET" action="{{ route('staff-cards.export.index') }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <label class="grid gap-2">
                <span class="text-sm font-black text-slate-800">Cơ sở</span>
                <div class="flex flex-wrap gap-2">
                    <select name="branch_id" class="min-h-12 min-w-64 flex-1 rounded-xl border-slate-200 bg-slate-50 text-sm">
                        @foreach ($branches as $branchOption)
                        <option value="{{ $branchOption->id }}" @selected($branch?->id === $branchOption->id)>{{ $branchOption->name }}</option>
                        @endforeach
                    </select>
                    <button class="min-h-12 rounded-xl bg-indigo-600 px-5 text-sm font-black text-white">Tải danh sách</button>
                </div>
            </label>
        </form>
        @elseif ($branch)
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-xs font-black uppercase text-slate-400">Cơ sở được phép</p>
            <p class="mt-1 text-lg font-black text-slate-900">{{ $branch->name }}</p>
        </section>
        @endif

        @if ($branch)
        <form
            method="POST"
            action="{{ route('staff-cards.export.preview') }}"
            class="space-y-5"
            x-data="{ selectionMode: '{{ old('selection_mode', 'all_active') }}', selected: {{ \Illuminate\Support\Js::from(old('user_ids', [])) }} }">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branch->id }}">

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="font-black text-slate-900">1. Chọn nhân sự</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer gap-3 rounded-2xl border border-slate-200 p-4">
                        <input type="radio" name="selection_mode" value="all_active" x-model="selectionMode" class="mt-1 text-indigo-600">
                        <span>
                            <strong class="block text-slate-900">Toàn bộ đang hoạt động</strong>
                            <small class="text-slate-500">{{ $employees->count() }} nhân sự trong phạm vi được phép</small>
                        </span>
                    </label>
                    <label class="flex cursor-pointer gap-3 rounded-2xl border border-slate-200 p-4">
                        <input type="radio" name="selection_mode" value="selected" x-model="selectionMode" class="mt-1 text-indigo-600">
                        <span>
                            <strong class="block text-slate-900">Chọn từng người</strong>
                            <small class="text-slate-500">Chỉ xuất các nhân sự được đánh dấu</small>
                        </span>
                    </label>
                </div>

                <div x-show="selectionMode === 'selected'" x-cloak class="mt-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-black text-slate-800">Danh sách nhân sự</p>
                        <div class="flex gap-2">
                            <button type="button" @click="selected = {{ \Illuminate\Support\Js::from($employees->pluck('id')->map(fn ($id) => (string) $id)->values()) }}" class="text-sm font-bold text-indigo-600">Chọn tất cả</button>
                            <button type="button" @click="selected = []" class="text-sm font-bold text-slate-500">Bỏ chọn</button>
                        </div>
                    </div>
                    <div class="grid max-h-[430px] gap-2 overflow-y-auto rounded-2xl bg-slate-50 p-3 sm:grid-cols-2">
                        @forelse ($employees as $employee)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl bg-white p-3 ring-1 ring-slate-200">
                            <input type="checkbox" name="user_ids[]" value="{{ $employee->id }}" x-model="selected" class="rounded text-indigo-600">
                            <span class="min-w-0">
                                <strong class="block truncate text-sm text-slate-900">{{ $employee->name }}</strong>
                                <small class="text-slate-500">{{ $employee->employee_code }} · {{ match ($employee->role) { 'manager' => 'Quản lý', 'cashier' => 'Thu ngân', default => 'Phục vụ' } }}</small>
                            </span>
                        </label>
                        @empty
                        <p class="p-4 text-sm text-slate-500">Không có nhân sự đang hoạt động.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <h2 class="font-black text-slate-900">2. Chọn mẫu thẻ</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    @foreach ([
                    'horizontal' => [
                    'Mẫu ngang',
                    '80 × 24 mm · tối đa 20 thẻ trên một trang A4',
                    ],
                    'vertical' => [
                    'Bảng đánh giá A5',
                    '148 × 210 mm · mỗi nhân sự một trang A5',
                    ],
                    'both' => [
                    'Cả hai mẫu',
                    'Tải ZIP gồm PDF thẻ ngang và PDF bảng A5',
                    ],
                    ] as $value => [$label, $hint])
                    <label class="flex cursor-pointer gap-3 rounded-2xl border border-slate-200 p-4">
                        <input type="radio" name="template" value="{{ $value }}" @checked(old('template', 'horizontal' )===$value) class="mt-1 text-indigo-600">
                        <span>
                            <strong class="block text-slate-900">{{ $label }}</strong>
                            <small class="text-slate-500">{{ $hint }}</small>
                        </span>
                    </label>
                    @endforeach
                </div>
            </section>

            <button type="submit" @disabled($employees->isEmpty()) class="w-full rounded-2xl bg-gradient-to-r from-indigo-600 to-blue-600 px-5 py-4 text-sm font-black text-white shadow-lg disabled:cursor-not-allowed disabled:opacity-50">
                Xem trước danh sách
            </button>
        </form>
        @else
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500">Không có cơ sở trong phạm vi quản lý.</div>
        @endif
    </div>
</x-app-layout>