<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Simulador WhatsApp · AgendaLlena</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-100 antialiased">
<div class="mx-auto flex h-screen max-w-md flex-col px-4 py-4">
    <div class="rounded-t-lg bg-emerald-700 px-4 py-3 text-white">
        <div class="flex items-center justify-between">
            <h1 class="font-semibold">Simulador de WhatsApp</h1>
            <span id="estado" role="status" aria-label="Estado de la conversación" class="rounded bg-emerald-900 px-2 py-0.5 text-xs">—</span>
        </div>
        <p class="mt-1 text-xs text-emerald-100">Solo desarrollo · Graph API → {{ $graphUrl }}</p>
    </div>

    <div class="grid grid-cols-2 gap-2 border-x border-gray-200 bg-white p-3 text-xs">
        <label class="col-span-2">Negocio (phone_number_id)
            <select id="numero" class="mt-1 w-full rounded border border-gray-300 px-2 py-1">
                @forelse ($numeros as $n)
                    <option value="{{ $n['numero'] }}">{{ $n['negocio'] }} · {{ $n['numero'] }}</option>
                @empty
                    <option value="">Ningún negocio con WhatsApp: corré php artisan dev:whatsapp:preparar</option>
                @endforelse
            </select>
        </label>
        <label>Teléfono del cliente
            <input id="from" value="5491100000001" class="mt-1 w-full rounded border border-gray-300 px-2 py-1">
        </label>
        <label>Nombre
            <input id="nombre" value="Cliente de prueba" class="mt-1 w-full rounded border border-gray-300 px-2 py-1">
        </label>
        <label class="col-span-2">El próximo envío del bot a Meta…
            <select id="falla" class="mt-1 w-full rounded border border-gray-300 px-2 py-1">
                <option value="">sale bien</option>
                <option value="rate_limit">falla con rate limit (130429), se reintenta</option>
                <option value="rechazo">es rechazado (131026)</option>
            </select>
        </label>
    </div>

    <div id="chat" role="log" aria-label="Chat" class="flex-1 space-y-2 overflow-y-auto border-x border-gray-200 bg-[#efeae2] p-3"></div>

    <form id="form" class="flex gap-2 rounded-b-lg border border-gray-200 bg-white p-2">
        <input id="texto" autocomplete="off" aria-label="Mensaje" placeholder="Escribí un mensaje"
               class="flex-1 rounded-full border border-gray-300 px-4 py-2 text-sm">
        <button class="rounded-full bg-emerald-600 px-4 text-sm font-medium text-white">Enviar</button>
    </form>
    <p id="error" role="alert" class="mt-2 text-xs text-red-700"></p>
</div>

<script nonce="{{ Vite::cspNonce() }}">
const BOTONES_PLANTILLA = @json($botonesPlantilla);
const $ = (id) => document.getElementById(id);
const csrf = document.querySelector('meta[name=csrf-token]').content;
let firma = '';

async function post(url, body) {
    const r = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
        body: JSON.stringify(body),
    });
    const datos = await r.json().catch(() => ({}));
    $('error').textContent = r.ok ? '' : (datos.error || datos.message || `HTTP ${r.status}`);
    return r.ok;
}

function enviar(extra) {
    return post('{{ route('dev.whatsapp.enviar') }}', {
        numero: $('numero').value, from: $('from').value, nombre: $('nombre').value, ...extra,
    }).then(cargar);
}

function boton(texto, onClick) {
    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = texto;
    b.className = 'mt-1 block w-full rounded border border-emerald-200 bg-white px-2 py-1 text-sm text-emerald-700 hover:bg-emerald-50';
    b.onclick = onClick;
    return b;
}

function burbuja(m) {
    const saliente = m.direction === 'outbound';
    const fila = document.createElement('div');
    fila.className = 'flex ' + (saliente ? 'justify-start' : 'justify-end');
    const b = document.createElement('div');
    b.className = 'max-w-[85%] whitespace-pre-wrap rounded-lg px-3 py-2 text-sm shadow-sm ' + (saliente ? 'bg-white' : 'bg-[#d9fdd3]');
    b.textContent = m.content ?? '';

    const p = m.payload || {};
    if (saliente && p.type === 'interactive') {
        const i = p.interactive;
        if (i.type === 'button') {
            for (const x of i.action.buttons) {
                b.appendChild(boton(x.reply.title, () => enviar({tipo: 'button', id: x.reply.id, titulo: x.reply.title})));
            }
        } else if (i.type === 'list') {
            for (const s of i.action.sections) {
                if (s.title) { const t = document.createElement('div'); t.className = 'mt-2 text-xs text-gray-500'; t.textContent = s.title; b.appendChild(t); }
                for (const row of s.rows) {
                    b.appendChild(boton(row.title + (row.description ? ' · ' + row.description : ''),
                        () => enviar({tipo: 'list', id: row.id, titulo: row.title})));
                }
            }
        }
    }
    if (saliente && p.type === 'template') {
        const t = document.createElement('div');
        t.className = 'mt-1 text-xs text-gray-400';
        t.textContent = 'plantilla: ' + p.template.name;
        b.appendChild(t);
        for (const c of p.template.components || []) {
            if (c.type !== 'button') continue;
            const titulo = BOTONES_PLANTILLA[c.index] || ('Botón ' + c.index);
            b.appendChild(boton(titulo, () => enviar({tipo: 'plantilla', id: c.parameters[0].payload, titulo})));
        }
    }
    fila.appendChild(b);
    return fila;
}

async function cargar() {
    if (!$('numero').value) return;
    const q = new URLSearchParams({numero: $('numero').value, from: $('from').value});
    const r = await fetch('{{ route('dev.whatsapp.mensajes') }}?' + q, {headers: {'Accept': 'application/json'}});
    if (!r.ok) return;
    const datos = await r.json();
    $('estado').textContent = datos.estado || 'sin conversación';
    const nueva = JSON.stringify(datos.mensajes.map(m => m.id));
    if (nueva === firma) return;
    firma = nueva;
    const chat = $('chat');
    chat.replaceChildren(...datos.mensajes.map(burbuja));
    chat.scrollTop = chat.scrollHeight;
}

$('form').onsubmit = (e) => {
    e.preventDefault();
    const texto = $('texto').value.trim();
    if (!texto) return;
    $('texto').value = '';
    enviar({tipo: 'texto', texto});
};
$('falla').onchange = () => post('{{ route('dev.whatsapp.falla') }}', {modo: $('falla').value || null});
$('numero').onchange = $('from').onchange = () => { firma = ''; cargar(); };

cargar();
setInterval(cargar, 1500);
</script>
</body>
</html>
