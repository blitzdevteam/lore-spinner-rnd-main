SOURCE IP: {!! $title ?? 'Untitled' !!}
@isset($author)
Author: {!! $author !!}
@endisset
@isset($format)
Format: {!! $format !!}
@endisset

CHAPTER BEING PROCESSED:
Chapter {!! $chapterPosition !!} of {!! $totalChapters !!}: "{!! $chapterTitle !!}"
Chapter ID: {!! $chapterId !!}

@if(!empty($previousChapterTitle))
CONTEXT ONLY: the previous chapter is Chapter {!! $chapterPosition - 1 !!}: "{!! $previousChapterTitle !!}"
@endif
@if(!empty($nextChapterTitle))
CONTEXT ONLY: the next chapter is Chapter {!! $chapterPosition + 1 !!}: "{!! $nextChapterTitle !!}"
@endif

@isset($chunkContext)
NOTE: This is {!! $chunkContext !!} of the chapter. Classify only the paragraphs below. Leave climax_fragment and resolution_fragment empty unless the story's climax or resolution occurs in them.
@endisset

----- BEGIN CHAPTER {!! $chapterPosition !!} PARAGRAPHS (P{!! $firstParagraph !!} to P{!! $lastParagraph !!}) -----
{!! $chapterContent !!}
----- END CHAPTER {!! $chapterPosition !!} PARAGRAPHS -----

Return a JSON fragment with chapter_id={!! $chapterId !!}, chapter_position={!! $chapterPosition !!}, story_spine_fragment, world_rules_fragments, a content_triage_log whose paragraph ranges cover P{!! $firstParagraph !!} to P{!! $lastParagraph !!} exactly once in order, and interactive_conversion_notes.
