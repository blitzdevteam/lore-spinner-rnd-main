BOILER FINALIZE INPUT

{{ json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}

Compile one standard cut. Preserve the Draft count, keys, order, event coverage, canonical choices, consequence bindings, and existing close. Return complete canonical scene cards and the final event-role map only.

Before returning, verify each card's `choice_ids` contains exactly the branching and emotional choice IDs whose authored `moment_key` equals that card's `moment_key`. Use an empty array where Choice Design authored no choice for that moment.
