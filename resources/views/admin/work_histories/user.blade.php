<x-app-layout>
    <div class="p-4">
        <div class="mx-auto max-w-7xl space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-slate-950">Lịch sử công tác của {{ $user->name }}</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $user->email }} · {{ $user->branch?->name ?? 'Chưa có chi nhánh' }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('users.edit', $user) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-100 px-4 text-sm font-bold text-slate-700">
                        Quay lại sửa nhân sự
                    </a>
                    <a href="{{ route('admin.work-histories.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-bold text-white">
                        Tất cả lịch sử
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.users.work-histories.index', $user) }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 md:grid-cols-5">
                <label class="grid gap-1 text-sm font-bold text-slate-600">
                    Chi nhánh
                    <select name="branch_id" class="rounded-xl border-slate-300">
                        <option value="">Tất cả</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-1 text-sm font-bold text-slate-600">
                    Loại thay đổi
                    <select name="change_type" class="rounded-xl border-slate-300">
                        <option value="">Tất cả</option>
                        <option value="branch" @selected(($filters['change_type'] ?? '') === 'branch')>Đổi chi nhánh</option>
                        <option value="role" @selected(($filters['change_type'] ?? '') === 'role')>Đổi role</option>
                        <option value="department" @selected(($filters['change_type'] ?? '') === 'department')>Đổi bộ phận</option>
                        <option value="status" @selected(($filters['change_type'] ?? '') === 'status')>Đổi trạng thái</option>
                    </select>
                </label>

                <label class="grid gap-1 text-sm font-bold text-slate-600">
                    Từ ngày
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="rounded-xl border-slate-300">
                </label>

                <label class="grid gap-1 text-sm font-bold text-slate-600">
                    Đến ngày
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="rounded-xl border-slate-300">
                </label>

                <div class="flex items-end gap-2">
                    <button class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-black text-white">
                        Lọc
                    </button>
                    <a href="{{ route('admin.users.work-histories.index', $user) }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-slate-100 px-4 text-sm font-black text-slate-700">
                        Xóa
                    </a>
                </div>
            </form>

            @include('admin.work_histories._list', ['showEmployee' => false, 'titleUser' => $user])
        </div>
    </div>
</x-app-layout>
