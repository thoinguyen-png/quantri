<x-app-layout>
    <div class="p-4">
        <div class="mx-auto max-w-7xl space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-slate-950">Lịch sử công tác</h1>
                    <p class="mt-1 text-sm text-slate-500">Theo dõi thay đổi chi nhánh, role và bộ phận của toàn hệ thống.</p>
                </div>

                <a href="{{ route('users.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-900 px-4 text-sm font-bold text-white">
                    Nhân sự
                </a>
            </div>

            <form method="GET" action="{{ route('admin.work-histories.index') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 md:grid-cols-6">
                <label class="grid gap-1 text-sm font-bold text-slate-600 md:col-span-2">
                    Nhân viên
                    <select name="user_id" class="rounded-xl border-slate-300">
                        <option value="">Tất cả</option>
                        @foreach ($users as $filterUser)
                            <option value="{{ $filterUser->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $filterUser->id)>
                                {{ $filterUser->name }} - {{ $filterUser->employee_code }}
                            </option>
                        @endforeach
                    </select>
                </label>

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

                <div class="flex gap-2 md:col-span-6">
                    <button class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-black text-white sm:flex-none">
                        Lọc
                    </button>
                    <a href="{{ route('admin.work-histories.index') }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-slate-100 px-4 text-sm font-black text-slate-700 sm:flex-none">
                        Xóa lọc
                    </a>
                </div>
            </form>

            @include('admin.work_histories._list', ['showEmployee' => true])
        </div>
    </div>
</x-app-layout>
