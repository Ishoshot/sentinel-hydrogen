<div class="relative" x-data="{ open: false }">
    <button
        @click="open = !open"
        class="flex items-center gap-2"
    >
        @if(auth()->user()->avatar_url)
            <img
                src="{{ auth()->user()->avatar_url }}"
                alt="{{ auth()->user()->name }}"
                class="w-8 h-8 rounded-full"
            >
        @else
            <div class="w-8 h-8 rounded-full bg-[#dbdbd7] dark:bg-[#3E3E3A] flex items-center justify-center text-sm font-medium">
                {{ substr(auth()->user()->name, 0, 1) }}
            </div>
        @endif
    </button>

    <div
        x-show="open"
        @click.away="open = false"
        class="absolute right-0 mt-2 w-48 bg-white dark:bg-[#161615] border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm shadow-lg z-50"
    >
        <div class="px-4 py-3 border-b border-[#e3e3e0] dark:border-[#3E3E3A]">
            <p class="text-sm font-medium">{{ auth()->user()->name }}</p>
            <p class="text-xs text-[#706f6c] dark:text-[#A1A09A] truncate">{{ auth()->user()->email }}</p>
        </div>

        <div class="py-1">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button
                    type="submit"
                    class="w-full text-left px-4 py-2 text-sm hover:bg-[#f5f5f4] dark:hover:bg-[#262625]"
                >
                    Sign out
                </button>
            </form>
        </div>
    </div>
</div>
