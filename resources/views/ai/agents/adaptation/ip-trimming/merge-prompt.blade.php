SOURCE IP: {!! $title ?? 'Untitled' !!}
@isset($author)
Author: {!! $author !!}
@endisset
Total chapters: {!! $totalChapters !!}
@if(!empty($playableProtagonist))

CANONICAL PLAYABLE PROTAGONIST (BINDING): The player avatar for this IP is {!! $playableProtagonist !!}. You MUST use this as the protagonist in story_spine.protagonist. Do not substitute a different character regardless of how prominent they appear in the source text.
@endif

STORY SPINE FRAGMENTS (one per chapter, in chapter order):

@foreach($spineFragments as $i => $fragment)
--- CHAPTER {!! $i + 1 !!} FRAGMENT ---
{!! json_encode($fragment, JSON_PRETTY_PRINT) !!}

@endforeach

Synthesize these fragments into a single unified story_spine. Return the protagonist, dramatic_question, world, major_turning_points (in chronological order, deduplicated), climax, resolution, and irreversible_events.
