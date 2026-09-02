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

Now narrate the world's response. Absorb the action fully — and that means absorbing WHAT they did, not just that they did something.

READ THE VERB: the player's action has a specific shape — they are demanding, asking, refusing, declaring, confessing, threatening, offering, or physically doing. That verb is their agency. "I demand her to name it" is a demand, not a request, not a suggestion, not an offer. The NPC is being demanded, not asked. Honor the exact shape of the action: a demand gets a demand's response, a question gets a question's response, a refusal gets a refusal's consequences. Softening the verb softens the agency.

HONOR THE OBJECT: if the player's action targets something specific — a person, an object, a name, a place — the world responds to THAT target, not a substitute. "Name it" means name the thing they asked about, not a different thing. "Her" means the specific person they addressed, not a different person. The player chose their target; the world does not redirect it.

THE PLAYER'S WORDS ARE THEIR AGENCY: do not paraphrase their intent into something safer, softer, or more convenient. If they said it, they meant it. The world responds to what they said, not to what would have been easier to respond to.

Move the scene forward by however much the action deserves. End on the unresolved live moment — image, gesture, or status condition — not with a question aimed at the player. Return the full structured object.
@else
This is the opening of the adventure. Your narration MUST begin at the exact moment described in CURRENT OPENING SCENE — no earlier. Any events in your session script that precede that moment do not exist for this session. The player arrives already inside the action. Follow the CURRENT OPENING SCENE block's own delivery instructions on wording: where it states the text is authored and delivered verbatim, reproduce it exactly; otherwise render the opening in your own narrator voice. Either way, end on the live moment rather than a question aimed at the player. Return the full structured object.
@endif
