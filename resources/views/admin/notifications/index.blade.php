@extends('admin.layouts.app')

@section('title', 'Notifiche - Stuart Admin')
@section('page_title', 'Notifiche')
@section('active_nav', 'notifications')

@section('content')
    <div class="grid gap-16">
        <div class="flex flex-col gap-12 rounded-10 border border-gray-mid bg-white p-16 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Centro notifiche</p>
                <h2 class="mt-4 text-24 font-black leading-tight tracking-normal">{{ $unreadCount }} non lette</h2>
            </div>

            <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="rounded-10 border border-gray-mid bg-white px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-black-nike transition hover:border-black-nike">
                    Segna tutte lette
                </button>
            </form>
        </div>

        <div class="grid gap-10">
            @forelse ($notifications as $notification)
                @php($data = $notification->data)
                <a
                    href="{{ route('admin.notifications.open', $notification) }}"
                    class="block rounded-10 border bg-white p-16 transition hover:border-black-nike {{ $notification->read_at ? 'border-gray-mid' : 'border-bullstar' }}"
                >
                    <div class="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-15 font-black leading-tight">{{ $data['title'] ?? 'Notifica' }}</p>
                            <p class="mt-6 text-14 font-semibold leading-relaxed text-gray">{{ $data['body'] ?? '' }}</p>
                        </div>
                        <p class="shrink-0 text-12 font-extrabold uppercase tracking-normal text-gray">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                </a>
            @empty
                <div class="rounded-10 border border-gray-mid bg-white p-20 text-14 font-bold text-gray">
                    Nessuna notifica.
                </div>
            @endforelse
        </div>

        {{ $notifications->links() }}
    </div>
@endsection
