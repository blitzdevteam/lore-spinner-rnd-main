SOURCE IP: {!! $title !!}, {!! $author !!}, {!! $year !!}, {!! $format !!}

CHAPTER BEING ANALYSED:
Chapter {!! $chapterPosition !!} of {!! $totalChapters !!}: "{!! $chapterTitle !!}"
Chapter ID: {!! $chapterId !!}

CRITICAL: This analysis uses the FULL ORIGINAL chapter content. Voice extraction requires the complete writing range including description and exposition. Do not ignore any passages.

----- BEGIN CHAPTER {!! $chapterPosition !!} ORIGINAL CONTENT -----
{!! $chapterContent !!}
----- END CHAPTER {!! $chapterPosition !!} ORIGINAL CONTENT -----

Return a JSON fragment with chapter_id={!! $chapterId !!}, chapter_position={!! $chapterPosition !!}, voice_observations, character_dialogue_observations, and ip_specific_ban_candidates.
