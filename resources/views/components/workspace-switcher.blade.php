@props(['workspace'])

@php
    $workspaces = auth()->user()->teamMemberships()->with('workspace')->get()->pluck('workspace');
@endphp

<div class="relative" x-data="{ open: false }">
    <button
        @click="open = !open"
        class="flex items-center gap-2 px-3 py-1.5 text-sm border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm hover:border-[#19140035] dark:hover:border-[#62605b]"
    >
        <span>{{ $workspace->name }}</span>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div
        x-show="open"
        @click.away="open = false"
        class="absolute right-0 mt-2 w-56 bg-white dark:bg-[#161615] border border-[#e3e3e0] dark:border-[#3E3E3A] rounded-sm shadow-lg z-50"
    >
        <div class="py-1">
            @foreach($workspaces as $ws)
                <form action="{{ route('workspaces.switch', $ws) }}" method="POST">
                    @csrf
                    <button
                        type="submit"
                        class="w-full text-left px-4 py-2 text-sm hover:bg-[#f5f5f4] dark:hover:bg-[#262625] {{ $ws->id === $workspace->id ? 'font-medium' : '' }}"
                    >
                        {{ $ws->name }}
                        @if($ws->id === $workspace->id)
                            <span class="text-green-600 ml-2">&#10003;</span>
                        @endif
                    </button>
                </form>
            @endforeach

            <div class="border-t border-[#e3e3e0] dark:border-[#3E3E3A] my-1"></div>

            <a
                href="{{ route('workspaces.create') }}"
                class="block px-4 py-2 text-sm hover:bg-[#f5f5f4] dark:hover:bg-[#262625]"
            >
                + Create new workspace
            </a>
        </div>
    </div>
</div>
