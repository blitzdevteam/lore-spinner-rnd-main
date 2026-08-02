# Prompt-to-Response Pipeline

1. The user enters a prompt, chooses a suggestion, or clicks **Continue** in `resources/js/pages/User/Games/Show.vue`.

2. The frontend sends a `POST` request to the `games.prompt.store` route with the prompt text, source prompt ID, and optional suggestion metadata.

3. `PromptController::store()` receives the request and validates the submitted action:
   - Confirms that the source prompt belongs to the game.
   - Confirms that it is the latest unanswered prompt.
   - Confirms that it belongs to the current session.
   - Validates the selected suggestion or branch when one was selected.

4. The controller creates a per-game turn lock so the same prompt cannot be submitted twice at the same time.

5. The controller marks the turn as pending in the cache and clears any previous turn error:
   - `game-turn-pending:{game_id}`
   - `game-turn-error:{game_id}`

6. The controller dispatches `ProcessGameTurnAction::handle()` with `afterResponse()`. The HTTP request returns immediately with `narrating: true`; the user does not wait for the AI request during this response.

7. The action initializes `game-turn-stream:{game_id}:{source_prompt_id}` in the expiring cache. It stores only narration text and status, with locked updates.

8. `GET games/{game}/turn-stream/{prompt}` is authenticated and ownership-checked. It emits only new narration chunks, heartbeats, and a final event after commit (or an error event).

9. The action reloads and validates the game turn, then loads its story/session context, conversation history, world state, alignment scaffold, and symbolic memory.

10. `ChaosEngineService::renderSystemPrompt()` builds the system prompt and the action selects the recent history and legacy/“Boiler” flow.

11. `ChaosEngineService::streamAgent()` uses Prism text streaming and requests the same JSON contract. The incremental parser publishes only the JSON `response` string while the complete envelope is consumed.

12. If streamed JSON is incomplete or invalid, the existing buffered `callAgent()` path is used. Partial stream text is never used for state.

13. `NarrationResultNormalizer::choices()` validates and normalizes the returned choices.

14. If playable moments are active, `PlayableMomentResolver::applyPlayerTurn()` commits the turn. Otherwise, `ProcessGameTurnAction::commitLegacyTurn()` commits the turn.

15. The database is updated inside a transaction:
   - The unanswered source row in the `prompts` table receives the user input in its `prompt` column.
   - The `games` row receives updated `world_state`, `alignment_scaffold`, and `symbolic_memory`.
   - Branch fields on `games` may be updated: `defining_choice_id`, `defining_path_label`, and `defining_choice_line`.
   - The `games` row receives updated climax/session status.
   - For playable moments, `current_playable_moment_id`, `playable_moment_turn_count`, and `moment_footnote` are updated.
   - A new response row is inserted into the `prompts` table with `source_prompt_id`, session/moment metadata, `response`, and normalized `choices`.

16. For playable moments, the transaction also records whether the current moment was retained, advanced, or completed, and whether the exit condition was met.

17. `UserActivityDay::record()` records the user’s activity for the day.

18. Narration metrics and logs are written, including model, response size, token usage, and elapsed time.

19. On success, the pending cache key is removed. On failure, the error is logged, an error cache key is written, and the pending key is removed.

20. After the initial POST succeeds, the frontend opens the SSE stream and retains `pollForTurnCompletion()` for reconciliation and reconnect fallback. Temporary text is cleared on the final event so it cannot duplicate the persisted response.

21. `GameController::show()` reloads the game and its prompts, including each prompt’s `prompt`, `response`, and `choices` fields, and returns them as Inertia props.

22. The frontend stops polling when the turn is no longer pending and the source prompt now contains the submitted user input. It then updates the page and displays the newly inserted `response` through `GameplayChatCard`.
