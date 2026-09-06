<x-app-layout>
    <div class="p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">Danh sách ca</h1>

            <a
                href="{{ route('shifts.create') }}"
                class="px-4 py-2 bg-blue-600 text-white rounded-xl font-bold"
            >
                + Thêm
            </a>
        </div>

        @if (session('success'))
            <div class="p-3 bg-green-100 text-green-700 rounded-xl">
                {{ session('success') }}
            </div>
        @endif

        <div class="space-y-3">
            @forelse ($shifts as $shift)
                <div class="bg-white p-4 rounded-2xl shadow">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-bold text-lg">
                                {{ $shift->name }}
                            </div>

                            <div class="mt-2 text-sm text-gray-600">
                                Giờ làm:
                                <strong>
                                    {{ $shift->start_at->format('H:i') }}
                                    -
                                    {{ $shift->end_at->format('H:i') }}
                                </strong>
                            </div>
                        </div>

                        <a
                            href="{{ route('shifts.edit', $shift) }}"
                            class="px-3 py-2 bg-gray-100 text-gray-800 rounded-xl text-sm font-bold"
                        >
                            Sửa
                        </a>
                    </div>

                    <div class="text-xs mt-3 bg-gray-100 inline-block px-3 py-1 rounded-full">
                        Cho phép trễ:
                        {{ $shift->late_after_minutes }} phút
                    </div>
                </div>
            @empty
                <div class="p-4 bg-white rounded-2xl shadow text-gray-500">
                    Chưa có ca làm.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
