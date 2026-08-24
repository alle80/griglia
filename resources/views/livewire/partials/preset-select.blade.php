{{--
    Select del modello o dell'effort di un task (task 641): «vuoto» = eredita il valore della lista.
    Stessa forma della select dell'agente, così nella riga e nel modale il gesto è lo stesso.
    Variabili attese:
      $todo           il todo
      $field          'model' oppure 'effort'
      $options        catalogo value => label offerto dall'agente del task
      $current        valore proprio del task ('' = eredita)
      $badge          testo mostrato quando il task è in lavorazione (o vuoto: niente badge)
      $change         espressione per wire:change (es. "setTodoModel(12, $event.target.value)")
      $inheritLabel   testo dell'opzione «eredita»
      $class          classi aggiuntive del select (opzionale)
--}}
@if ($options)
    @php($label = __('griglia::t.'.$field.'_of_task'))
    @if ($todo->working)
        @if ($badge ?? '')
            <span class="db-agent-select db-preset-{{ $field }} {{ $class ?? '' }}" title="{{ $label }}" aria-label="{{ $label }}">{{ $badge }}</span>
        @endif
    @else
        <select
            class="db-agent-select db-preset-{{ $field }} cursor-pointer bg-transparent {{ $class ?? '' }}"
            wire:change="{{ $change }}"
            title="{{ $label }}"
            aria-label="{{ $label }}"
            x-on:click.stop
        >
            <option value="" @selected(! $current)>{{ $inheritLabel }}</option>
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" @selected($current === $value)>{{ $text }}</option>
            @endforeach
        </select>
    @endif
@endif
