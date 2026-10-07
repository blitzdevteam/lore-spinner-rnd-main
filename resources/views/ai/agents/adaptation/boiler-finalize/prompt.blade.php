BOILER FINALIZE INPUT

{{ json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

@include('ai.agents.adaptation._protagonist-rule', ['protagonist' => $context['protagonist']])

ONE CONTINUOUS SEQUENCE (the runtime plays the cold open, then each card in order, and never goes back):
- The first card's opening_image begins where the cold open above ends. It never retells anything the cold open has told.
- Each card's opening_image begins where the previous card's next_bridge leaves off. A next_bridge hands off to the next card's situation without staging its opening beat, so nothing is told twice.
- An opening_image sets up its room and stops before the room's own choice or outcome. It never plays the outcome a choice on that card will decide.
- On an interlude card (a scene the protagonist is not in), opening_image, runtime_goal, live_pressure and next_bridge are third person, and player_desire and player_affordances are the protagonist's own, where they actually are during it.
- Dialogue is quoted and attributed to its speaker.

Compile one standard cut. Preserve the Draft count, keys, order, event coverage, canonical choices, consequence bindings, and existing close. Return complete canonical scene cards and the final event-role map only.

Before returning, verify each card's `choice_ids` contains exactly the branching and emotional choice IDs whose authored `moment_key` equals that card's `moment_key`. Use an empty array where Choice Design authored no choice for that moment.
