@php
    $targetValue = (string) ($selectedTarget ?? '');
    $currentLineId = (string) ($currentLine ?? '');
@endphp
@if(!empty($lineQueues))
    <optgroup label="{{ __('Cola del canal actual') }}">
        @foreach($lineQueues as $queue)
            <option value="{{ $queue['entity_id'] }}"
                    data-type="{{ $queue['entity_type'] }}"
                    {{ $targetValue === (string)$queue['entity_id'] ? 'selected' : '' }}>
                {{ $queue['label'] }}
            </option>
        @endforeach
    </optgroup>
@endif
<optgroup label="{{ __('Otro Open Channel') }}">
    @foreach($channelsForTransfer as $channel)
        @if($currentLineId !== '' && (string)($channel['ID'] ?? '') === $currentLineId)
            @continue
        @endif
        <option value="{{ $channel['ID'] }}"
                data-type="line"
                {{ $targetValue === (string)($channel['ID'] ?? '') ? 'selected' : '' }}>
            {{ $channel['LINE_NAME'] ?? __('Open Channel :id', ['id' => $channel['ID']]) }}
        </option>
    @endforeach
</optgroup>