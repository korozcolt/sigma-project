<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Tipo:</p>
            <p class="text-sm">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    {{ $template->type === 'birthday' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $template->type === 'reminder' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $template->type === 'campaign' ? 'bg-blue-100 text-blue-800' : '' }}
                    {{ $template->type === 'custom' ? 'bg-gray-100 text-gray-800' : '' }}
                ">
                    {{ ucfirst($template->type) }}
                </span>
            </p>
        </div>

        <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Canal:</p>
            <p class="text-sm">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    {{ $template->channel === 'whatsapp' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $template->channel === 'sms' ? 'bg-blue-100 text-blue-800' : '' }}
                    {{ $template->channel === 'email' ? 'bg-yellow-100 text-yellow-800' : '' }}
                ">
                    {{ strtoupper($template->channel) }}
                </span>
            </p>
        </div>
    </div>

    @if($template->subject)
    <div>
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Asunto:</p>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $template->subject }}</p>
    </div>
    @endif

    <div>
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Contenido:</p>
        <div class="mt-1 p-3 bg-gray-50 dark:bg-gray-800 rounded-md">
            <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ $template->content }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Límites:</p>
            <p class="text-xs text-gray-600 dark:text-gray-400">
                • {{ $template->max_per_voter_per_day }} mensajes/votante/día<br>
                • {{ $template->max_per_campaign_per_hour }} mensajes/campaña/hora
            </p>
        </div>

        <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Horario:</p>
            <p class="text-xs text-gray-600 dark:text-gray-400">
                {{ $template->allowed_start_time }} - {{ $template->allowed_end_time }}
            </p>
        </div>
    </div>

    @if($template->allowed_days)
    <div>
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Días Permitidos:</p>
        <div class="flex gap-1 mt-1">
            @foreach($template->allowed_days as $day)
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                    {{ ucfirst($day) }}
                </span>
            @endforeach
        </div>
    </div>
    @endif

    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-3">
        <p class="text-xs font-medium text-blue-800 dark:text-blue-200">Variables disponibles:</p>
        <p class="text-xs text-blue-600 dark:text-blue-300 mt-1">
            {{'{{'}}nombre{{'}}'}}, {{'{{'}}edad{{'}}'}}, {{'{{'}}candidato{{'}}'}}, {{'{{'}}fecha{{'}}'}},
            {{'{{'}}barrio{{'}}'}}, {{'{{'}}municipio{{'}}'}}
        </p>
    </div>
</div>
