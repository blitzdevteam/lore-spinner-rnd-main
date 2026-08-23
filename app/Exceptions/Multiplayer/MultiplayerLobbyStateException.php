<?php

declare(strict_types=1);

namespace App\Exceptions\Multiplayer;

use RuntimeException;

final class MultiplayerLobbyStateException extends RuntimeException
{
    public static function isNotWaiting(): self
    {
        return new self('This lobby is no longer waiting to start.');
    }

    public static function onlyHostCanStart(): self
    {
        return new self('Only the lobby host can start the story.');
    }

    public static function needsMorePlayers(): self
    {
        return new self('At least three players must be in the lobby before the story can start.');
    }

    public static function playersAreNotReady(): self
    {
        return new self('Every player must be ready before the story can start.');
    }

    public static function isNotStarted(): self
    {
        return new self('The Arbiter story has not started yet.');
    }

    public static function isNotCollectingProposals(): self
    {
        return new self('This turn is no longer accepting proposals.');
    }

    public static function onlyNonArbitersCanPropose(): self
    {
        return new self('The current Arbiter waits for the other players to propose their choices.');
    }

    public static function proposalAlreadySubmitted(): self
    {
        return new self('You have already submitted a proposal for this turn.');
    }

    public static function onlyCurrentArbiterCanChoose(): self
    {
        return new self('Only the current Arbiter can choose the next action.');
    }

    public static function proposalsAreNotReady(): self
    {
        return new self('Wait for every non-Arbiter player to submit a proposal.');
    }

    public static function proposalIsNotAvailable(): self
    {
        return new self('That proposal is not available for this turn.');
    }

    public static function onlyHostCanAdvanceSession(): self
    {
        return new self('Only the lobby host can advance to the next chapter.');
    }

    public static function sessionIsNotComplete(): self
    {
        return new self('The current chapter is not ready to advance yet.');
    }

    public static function storyIsComplete(): self
    {
        return new self('This Arbiter story is already complete.');
    }

    public static function nextSessionIsNotAvailable(): self
    {
        return new self('The next chapter is not available for this story.');
    }
}
