<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Adaptation\RuntimeNarratorAssemblyJob;
use App\Models\PlayableMoment;
use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PatchEmmaPlayableCommand extends Command
{
    protected $signature = 'stories:patch-emma';

    protected $description = 'Patch Emma to second-person protagonist POV and fix the reviewed scene, choice, handoff and pacing defects';

    public function handle(): int
    {
        $story = Story::where('slug', 'emma')->firstOrFail();
        $adaptation = $story->adaptation;
        $report = [];

        DB::transaction(function () use ($story, $adaptation, &$report) {
            $note = function (string $line) use (&$report) {
                $report[] = $line;
            };

            /* ---------- 1. Voice profile: second person present, no semicolons or em dashes ---------- */
            $vp = $adaptation->voice_profile;

            $vp['anchor_card'] = array_map(function ($line) {
                if (preg_match('/^Punctuation:/i', $line)) {
                    return "Punctuation: never write a semicolon or an em dash, in narration or in dialogue. Carry the author's qualification, interruption and afterthought with commas, full stops and balanced subordinate clauses.";
                }
                if (preg_match('/third-person past/i', $line)) {
                    return "Narrate in second person, present tense, to the player as Emma Woodhouse. \"You\" is always Emma and only Emma. Keep the author's close focalization and ironic social judgment inside Emma's own perceptions. Every other character is he, she or they. Never drift into third-person past, neutral omniscience or first-person confession.";
                }

                return $line;
            }, $vp['anchor_card']);

            $vp['runtime_self_check'] = array_map(function ($line) {
                if (preg_match('/^1\.\s*Search punctuation/i', $line)) {
                    return '1. Search punctuation. Remove every semicolon and every em dash. Restructure with commas, full stops or balanced clauses so the qualification they carried survives.';
                }
                if (preg_match('/third-person past/i', $line)) {
                    return '7. Check narrator stance: still second person, present tense, addressed to the player as Emma, close to her consciousness, with free indirect irony anchored to her own judgment. No other character is ever "you".';
                }

                return $line;
            }, $vp['runtime_self_check']);

            data_set($vp, 'author_voice_dna_profile.sentence_level_patterns.punctuation_habits', 'Source habit: semicolons and em dashes are frequent, commas are dense, parentheses occasional, exclamation marks mostly in dialogue. Runtime rule: LoreSpinner bans semicolons and em dashes, so render the same qualification and afterthought with commas, full stops and balanced clauses.');
            data_set($vp, 'author_voice_dna_profile.narrator_perspective.point_of_view', "Source: third-person past, close to Emma. Runtime: second person, present tense, addressed to the player as Emma Woodhouse. \"You\" is Emma. The narrator's ironic social authority lives inside her perceptions and judgments.");
            data_set($vp, 'author_voice_dna_profile.narrator_perspective.tense', 'Runtime: present tense. The source past tense is reference only.');

            foreach ((array) data_get($vp, 'author_voice_dna_profile.dialogue_fingerprint_per_character', []) as $i => $speaker) {
                $rhythm = (string) ($speaker['speech_rhythm'] ?? '');
                $rhythm = str_replace(['self-corrective dashes', 'exclamations, dashes, and self-display', 'linked by dashes'], ['self-corrective turns', 'exclamations, run-on clauses and self-display', 'linked by quick commas'], $rhythm);
                $vp['author_voice_dna_profile']['dialogue_fingerprint_per_character'][$i]['speech_rhythm'] = $rhythm;
            }

            foreach ((array) data_get($vp, 'master_rule_1_hard_bans.ip_specific_bans', []) as $i => $ban) {
                if (str_contains((string) ($ban['positive_replacement'] ?? ''), 'semicolons')) {
                    $vp['master_rule_1_hard_bans']['ip_specific_bans'][$i]['positive_replacement'] = 'Use complete sentences with qualification, commas and balanced clauses.';
                }
            }

            foreach ((array) data_get($vp, 'build_time_qa_protocol.judgment_checks', []) as $i => $check) {
                if (str_contains((string) $check, 'third-person past')) {
                    $vp['build_time_qa_protocol']['judgment_checks'][$i] = 'NARRATOR COMPLIANCE: passage must be second person, present tense, addressed to the player as Emma, carrying the socially authoritative, free-indirect Austen irony inside her perceptions. Fail if the stance drifts to third-person past, cinematic immediacy or modern intimacy.';
                }
            }
            if (is_string(data_get($vp, 'build_time_qa_protocol.decay_test_procedure'))) {
                $vp['build_time_qa_protocol']['decay_test_procedure'] = str_replace('loss of semicolon/dash modulation', 'loss of qualified-clause modulation', $vp['build_time_qa_protocol']['decay_test_procedure']);
            }

            $vp['voice_anchor'] = array_map(function ($exemplar) {
                if (! is_array($exemplar)) {
                    return $exemplar;
                }
                if (str_contains((string) ($exemplar['source'] ?? ''), 'between Knightley and Mrs. Weston')) {
                    return [
                        'mode' => 'dialogue_bearing',
                        'source' => "Emma and Mr. Knightley quarrel over Harriet's refusal of Robert Martin (Ch.8).",
                        'techniques' => 'dialogic argument as exposition, balanced antithesis, formal social diction',
                        'prose' => "“Not Harriet’s equal!” exclaims Mr. Knightley, loudly and warmly. With calmer asperity he adds, a few moments afterwards, “No, he is not her equal indeed, for he is as much her superior in sense as in situation. Emma, your infatuation about that girl blinds you.”\n\nYou cannot help wondering at his knowing so little of you as to say any such thing. “What! think a farmer, and with all his sense and all his merit Mr. Martin is nothing more, a good match for my intimate friend! You are not just to Harriet’s claims. The sphere in which she moves is much above his. It would be a degradation.”\n\n“A degradation to illegitimacy and ignorance, to be married to a respectable, intelligent gentleman-farmer!”\n\nIt is most convenient to you not to make a direct reply to this assertion. You choose rather to take up your own line of the subject again.",
                        'session_relevance' => 'all_sessions',
                    ];
                }
                $prose = (string) ($exemplar['prose'] ?? '');
                $prose = preg_replace('/([?!])\s*—\s*/u', '$1 ', $prose);
                $prose = preg_replace('/\s*—\s*/u', ', ', $prose);
                $prose = preg_replace('/\s*;\s*/u', ', ', $prose);
                $prose = preg_replace('/,\s*,/u', ',', $prose);
                $exemplar['prose'] = $prose;

                return $exemplar;
            }, $vp['voice_anchor']);

            $adaptation->voice_profile = $vp;
            $adaptation->save();
            $note('voice_profile: second person present, punctuation bans, Knightley exemplar replaced');

            /* ---------- 2. Scene cards ---------- */
            $moments = PlayableMoment::query()
                ->whereHas('cut', fn ($q) => $q->where('story_id', $story->id)->where('status', 'finalized'))
                ->with('cut')
                ->get()
                ->keyBy('moment_key');

            $patchCard = function (string $key, callable $change) use ($moments, $note) {
                $moment = $moments->get($key);
                if (! $moment) {
                    $note("MISSING card {$key}");

                    return;
                }
                $card = (array) $moment->scene_card;
                $patched = $change($card);
                if ($patched === $card) {
                    $note("unchanged {$key}");

                    return;
                }
                $moment->scene_card = $patched;
                $moment->save();
                $note("patched {$key}");
            };

            $patchCard('S1_M01', function (array $c) {
                $c['treatment'] = "The room opens on the provision you chose at the bell, the paper or your father's side, and plays it out once. Tea comes, and your father says exactly what he said at dinner. As he pities poor Miss Taylor, you recall her sixteen years of care and kindness, and the gratitude you owe her. The wedding visit, the carriage, James and Hannah are settled here in talk, and they answer what you actually did.";
                $c['opening_image'] = 'The tea-things are brought in. Your father, already answered by what you have just done, takes his cup and begins once more upon poor Miss Taylor.';
                $c['canon_must_keep'][] = "Emma recalls Miss Taylor's sixteen years of care and kindness, and her gratitude for them";

                return $c;
            });

            $patchCard('S1_M05', function (array $c) {
                $c['exit_condition'] = 'The evening with Harriet has ended. You are determined to continue the acquaintance, and your wish to notice and improve her has taken the shape the player gave it.';

                return $c;
            });

            $patchCard('S1_M10', function (array $c) {
                $c['exit_condition'] = 'Mr. Martin has been met and surveyed. You have spoken against his want of gentility and held up Mr. Elton as a model, and Harriet has answered with mortified deference while still wondering that he forgot the book. Harriet has not agreed that Mr. Martin is no gentleman. You have fixed on Mr. Elton for her.';

                return $c;
            });

            $patchCard('S3_M01', function (array $c) {
                $c['opening_image'] = "The sixteen miles are accomplished. Mr. and Mrs. John Knightley, their five children and a competent number of nursery-maids reach Hartfield in safety, and the hall fills with a bustle your father's nerves could bear for no other cause.";

                return $c;
            });

            $patchCard('S3_M11', function (array $c) {
                $c['player_affordances'] = array_map(fn ($a) => str_contains((string) $a, 'wine')
                    ? "Suspect, privately, that Mr. Elton has been drinking too much of Mr. Weston's good wine"
                    : $a, (array) $c['player_affordances']);

                return $c;
            });

            $patchCard('S4_M02', function (array $c) {
                $c['treatment'] = "A morning walk with Harriet in which every subject returns to Mr. Elton. You speak of what the poor must suffer in winter and receive only a plaintive answer in his praise. At the door of Mrs. and Miss Bates you go in, as you seldom do, and seek safety in numbers, with your old reluctance and Mr. Knightley's hints both in mind. The call always happens. Only your manner of making it is yours.";
                $c['player_affordances'] = array_values(array_filter((array) $c['player_affordances'], fn ($a) => ! preg_match('/\bpass(ing)?\b/i', (string) $a)));
                $c['forbidden_expansions'][] = "You never pass the Bateses' door. The call is fixed on every path.";

                return $c;
            });

            $patchCard('S8_M01', function (array $c) {
                $c['opening_image'] = 'On the Hartfield lawn the next morning, the undertaking you chose has already been given its first form. The recollections of the ball are yours to enjoy, and the best of them is your little explanation with Mr. Knightley about the Eltons.';

                return $c;
            });

            $patchCard('S11_M04', function (array $c) {
                $c['player_affordances'] = [
                    'Laugh at his claim to have foreseen it',
                    'Answer his wish to be told when he may speak out, knowing how little a secret keeps in Highbury',
                    'Tell him what you believe Jane suspects',
                    'Hear the reported Vicarage remarks and choose how to take them',
                ];
                if (! str_contains((string) $c['treatment'], 'suspect')) {
                    $c['treatment'] = mb_rtrim((string) $c['treatment']).' He wonders aloud whether Jane suspects anything of it.';
                }
                $c['forbidden_expansions'][] = 'You cannot stop or delay the news. It passes from Mr. Weston to Jane and through Miss Bates to Highbury whatever you say.';

                return $c;
            });

            $metaPatterns = [
                '/\s*(?:This|The) room (?:settles|resolves) the \w+ branch\b[^.]*\./iu',
                '/\s*This is the (?:first|second|third|fourth|final) branch\./iu',
                '/\s*This is the relief the session has promised\./iu',
                '/\s*The trap is set in play\./iu',
            ];
            $scrub = function ($value) use (&$scrub, $metaPatterns) {
                if (is_array($value)) {
                    return array_map($scrub, $value);
                }
                if (! is_string($value)) {
                    return $value;
                }
                $clean = preg_replace($metaPatterns, '', $value);

                return $clean === $value ? $value : mb_trim($clean);
            };
            foreach ($moments as $moment) {
                $card = (array) $moment->scene_card;
                $clean = $scrub($card);
                if ($clean !== $card) {
                    $moment->scene_card = $clean;
                    $moment->save();
                    $note("meta lines removed {$moment->moment_key}");
                }
            }

            /* ---------- 3. Session artifacts ---------- */
            $sessions = $adaptation->sessionAdaptations()->orderBy('session_number')->get()->keyBy('session_number');

            $setOutcome = function (int $n, string $choiceId, string $label, string $text) use ($sessions, $note) {
                $session = $sessions[$n];
                $design = $session->session_choice_design;
                foreach ($design['branching_choices'] as $ci => $choice) {
                    if ($choice['choice_id'] !== $choiceId) {
                        continue;
                    }
                    foreach ($choice['options'] as $oi => $option) {
                        if ($option['label'] === $label) {
                            $design['branching_choices'][$ci]['options'][$oi]['outcome'] = $text;
                            $session->session_choice_design = $design;
                            $session->save();
                            $note("outcome {$choiceId} {$label}");

                            return;
                        }
                    }
                }
                $note("MISSING outcome {$choiceId} {$label}");
            };

            $setOutcome(1, 'S1_C4', 'B', "You determine that the distinction shall be made plainly. The commission deserves gratitude, but attachment requires a declaration before it can properly govern Harriet's expectations. Such a caution need not extinguish your own opinion. Indeed, you can be quite persuaded of Mr. Elton's preference while declining to make Harriet responsible for sharing the persuasion. To have devised a restraint which leaves the agreeable prospect entire is very satisfactory. The words are not yet spoken, and no merit can therefore be claimed for their effect. Harriet continues to enjoy the distinction of the portrait without knowing what qualification you intend to place beside it. You prepare to reserve conjecture to yourself, while she draws her chair nearer for conversation.");

            $setOutcome(7, 'S7_C1', 'B', "Your father's pleasure in your nearness supplies a favourable beginning, and you make the dinner appear an extension of his ordinary comforts rather than an interruption of them. The hour will be convenient, and every attention will be paid to him. The company, you assure him, need not be noisy. These are excellent assurances, and their excellence is not diminished, in his estimation, by the particulars remaining to be arranged. He repeats an objection about late hours, which you answer with sufficient tenderness to make repetition unnecessary for the present. After ten minutes he feels no unwillingness. You have obtained his consent without summoning anybody. The work of deserving it is still yours. His hand rests upon his chair as he begins the familiar objection to the bottom of the table.");

            $close11 = $sessions[11]->session_close_design;
            $close11['hook_transition'] = "For a little while, you have nothing to explain, and may enjoy your happiness without recommending it to anybody. Harriet's separate household has its claim upon your attention, and so has Mr. Knightley's accommodation at Hartfield. Miss Bates's name, too, must find its place among the wishes of your friends.";
            $sessions[11]->session_close_design = $close11;
            $sessions[11]->save();
            $note('S11 hook_transition');

            foreach ($sessions as $n => $session) {
                $close = (array) ($session->session_close_design['session_end_choice'] ?? []);
                $design = $session->session_choice_design;
                $last = array_key_last($design['branching_choices']);
                $changed = false;
                foreach ($design['branching_choices'][$last]['options'] as $oi => $option) {
                    $closeText = $close['option_'.mb_strtolower($option['label'])]['next_session_opens'] ?? null;
                    if ($closeText && ($option['next_session_opens'] ?? null) !== $closeText) {
                        $design['branching_choices'][$last]['options'][$oi]['next_session_opens'] = $closeText;
                        $changed = true;
                    }
                }
                if ($changed) {
                    $session->session_choice_design = $design;
                    $session->save();
                    $note("S{$n} end-hook next_session_opens synced to close");
                }
            }

            $cut3 = $sessions[3]->boiler_final_cut;
            $cut3['playable_promise'] = "At Hartfield you arrange everybody's comfort and trust your own reading of every heart in the room. Then the man you meant for Harriet declares himself to you in a shut carriage, and you must own the error and tell Harriet, without leaving her to bear the whole mortification of it.";
            $sessions[3]->boiler_final_cut = $cut3;
            $sessions[3]->save();
            $note('S3 playable_promise');

            /* ---------- 4. Pacing: bring words per turn within each session's narration budget ---------- */
            foreach ($sessions as $n => $session) {
                $budgetText = (string) data_get($session->session_architecture, 'content_budget.narration_token_budget', '');
                if (! preg_match('/(\d[\d,]*)(?:\s*[–-]\s*(\d[\d,]*))?\s*(?:narrator[- ]output\s+|narrator\s+)?words/u', $budgetText, $m)) {
                    $note("S{$n} pacing: budget not found");

                    continue;
                }
                $budget = (int) str_replace(',', '', ($m[2] ?? '') !== '' ? $m[2] : $m[1]);
                $cards = $moments->filter(fn ($moment) => (int) $moment->cut->session_number === (int) $n);
                $total = $cards->sum(fn ($moment) => max(1, (int) data_get($moment->scene_card, 'turn_budget.target_turns', 1)) * (int) data_get($moment->scene_card, 'word_budget.target_per_turn', 0));
                if ($total <= $budget * 1.15) {
                    continue;
                }
                $factor = $budget / $total;
                $after = 0;
                foreach ($cards as $moment) {
                    $card = (array) $moment->scene_card;
                    $card['word_budget']['target_per_turn'] = max(90, (int) round($card['word_budget']['target_per_turn'] * $factor));
                    $after += max(1, (int) $card['turn_budget']['target_turns']) * $card['word_budget']['target_per_turn'];
                    $moment->scene_card = $card;
                    $moment->save();
                }
                $note("S{$n} pacing: {$total} -> {$after} words (budget {$budget})");
            }

            /* ---------- 5. Rebuild every runtime narrator prompt from the patched data ---------- */
            foreach ($sessions->keys() as $n) {
                app()->call([new RuntimeNarratorAssemblyJob($story->fresh(), (int) $n), 'handle']);
                $prompt = (string) $adaptation->sessionAdaptations()->where('session_number', $n)->value('runtime_narrator_prompt');
                $note(sprintf('S%d prompt rebuilt: %s chars, old POV rule x%d, meta lines x%d',
                    $n,
                    number_format(mb_strlen($prompt)),
                    preg_match_all('/Narrator stays in third-person past|still third-person past/u', $prompt),
                    preg_match_all('/session has promised|room settles the \w+ branch|This is the second branch|real work of the session/u', $prompt)));
            }
        });

        echo implode(PHP_EOL, $report).PHP_EOL.'DONE'.PHP_EOL;

        return self::SUCCESS;
    }
}
