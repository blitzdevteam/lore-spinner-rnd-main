<?php

declare(strict_types=1);

it('echoes typed player input immediately via pendingSelection before the stream commits', function () {
    $show = file_get_contents(resource_path('js/pages/User/Games/Show.vue'));

    expect($show)
        ->toContain('pendingSelection.value[promptId] = text;')
        ->toContain('pendingSelection.value[source.id] = CONTINUE_MARKER;')
        ->toContain('pendingSelection.value[source.id] = prompt;')
        ->toContain('pendingSelection.value[promptId] = attempt.prompt;');
});
