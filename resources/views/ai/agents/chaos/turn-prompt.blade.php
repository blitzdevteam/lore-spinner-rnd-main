@php
    $protagonistName = $protagonist ?? 'the protagonist';
    $protagonistUpper = strtoupper($protagonistName);
@endphp
@if(!empty($conversationHistory))
STORY SO FAR:
@foreach($conversationHistory as $turn)
@if(($turn['role'] ?? '') === 'narrator')
[NARRATOR]: {{ $turn['text'] ?? '' }}
@else
[{{ strtoupper((string) ($turn['protagonist'] ?? $protagonistName)) }}]: {{ $turn['text'] ?? '' }}
@endif
@endforeach

@endif
@if(!empty($playerAction))
{{ $protagonistUpper }}'S ACTION: {{ $playerAction }}

Now narrate the world's response. Absorb the action fully. Move the scene forward by however much the action deserves. End on the unresolved live moment — image, gesture, or status condition — not with a question aimed at the player. Return the full structured object.
@else
This is the opening of the adventure. Your narration MUST begin at the exact moment described in CURRENT OPENING SCENE — no earlier. Any events in your session script that precede that moment do not exist for this session. The player arrives already inside the action. Follow the CURRENT OPENING SCENE block's own delivery instructions on wording: where it states the text is authored and delivered verbatim, reproduce it exactly; otherwise render the opening in your own narrator voice. Either way, end on the live moment rather than a question aimed at the player. Return the full structured object.
@endif
