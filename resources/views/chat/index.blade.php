@extends('layouts.app')

@section('title', 'Чат')

@section('wrap_class')
    {{ auth()->user()->isStoryteller() ? 'wide' : '' }}
@endsection

@section('content')
    <div class="top">
        <h1>Чат</h1>
        <span class="muted">
            {{ auth()->user()->name }}
            @if (auth()->user()->isStoryteller())
                · рассказчик
            @endif
            ·
            <form class="inline" method="post" action="{{ route('logout') }}">
                @csrf
                <button class="link" type="submit">Выйти</button>
            </form>
        </span>
    </div>

    <div class="{{ auth()->user()->isStoryteller() ? 'stage' : '' }}">
        <div>
            <div id="log" class="chat-log" aria-live="polite">
                @forelse ($messages as $message)
                    <article class="msg {{ $message->user_id === auth()->id() ? 'mine' : '' }}" data-id="{{ $message->id }}">
                        <div class="meta">{{ $message->user->name }} · {{ $message->created_at->timezone(config('app.timezone'))->format('H:i') }}</div>
                        <div>{{ $message->body }}</div>
                    </article>
                @empty
                    <p class="muted" id="empty">Пока пусто. Напишите первое сообщение.</p>
                @endforelse
            </div>

            <form id="composer" class="composer" method="post" action="{{ route('chat.messages.store') }}">
                @csrf
                <textarea name="body" maxlength="4000" required placeholder="Сообщение…"></textarea>
                <button type="submit">Отправить</button>
            </form>
        </div>

        @if (auth()->user()->isStoryteller())
            <aside class="card storyteller-panel">
                <h2>Панель рассказчика</h2>
                <p class="muted">Здесь позже появятся варианты реплик НПС: короткий промпт, три черновика, правка и отправка в чат от имени персонажа. Пока пусто — игроки эту панель не видят.</p>
            </aside>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    const log = document.getElementById('log');
    const form = document.getElementById('composer');
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const pollUrl = @json(route('chat.messages'));
    const postUrl = @json(route('chat.messages.store'));

    function lastId() {
        const items = log.querySelectorAll('[data-id]');
        if (!items.length) return 0;
        return Number(items[items.length - 1].dataset.id);
    }

    function append(msg) {
        const empty = document.getElementById('empty');
        if (empty) empty.remove();
        if (log.querySelector('[data-id="' + msg.id + '"]')) return;
        const el = document.createElement('article');
        el.className = 'msg' + (msg.mine ? ' mine' : '');
        el.dataset.id = msg.id;
        el.innerHTML = '<div class="meta"></div><div class="body"></div>';
        el.querySelector('.meta').textContent = msg.author + ' · ' + msg.created_at;
        el.querySelector('.body').textContent = msg.body;
        log.appendChild(el);
        log.scrollTop = log.scrollHeight;
    }

    log.scrollTop = log.scrollHeight;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = form.body.value.trim();
        if (!body) return;
        const res = await fetch(postUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ body }),
        });
        if (!res.ok) return;
        const data = await res.json();
        append(data.message);
        form.body.value = '';
        form.body.focus();
    });

    async function poll() {
        const res = await fetch(pollUrl + '?after_id=' + lastId(), {
            headers: { 'Accept': 'application/json' },
        });
        if (!res.ok) return;
        const data = await res.json();
        data.messages.forEach(append);
    }

    setInterval(poll, 3000);
</script>
@endpush
