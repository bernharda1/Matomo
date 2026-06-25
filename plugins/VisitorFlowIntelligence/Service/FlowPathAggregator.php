<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Plugins\VisitorFlowIntelligence\Domain\Dropoff;
use Piwik\Plugins\VisitorFlowIntelligence\Domain\Path;
use Piwik\Plugins\VisitorFlowIntelligence\Domain\Transition;

final class FlowPathAggregator
{
    /**
     * @param array<int, array<int, string>> $visitSteps
     * @return array{totalVisits:int, paths:array<int, array<string, mixed>>, transitions:array<int, array<string, mixed>>, dropoffs:array<int, array<string, mixed>>}
     */
    public function aggregate(array $visitSteps, int $limit): array
    {
        $pathCounters = [];
        $transitionCounters = [];
        $sourceStepCounters = [];
        $stepOccurrenceCounters = [];
        $dropoffCounters = [];

        foreach ($visitSteps as $steps) {
            if ($steps === []) {
                continue;
            }

            foreach ($steps as $stepId) {
                if (!isset($stepOccurrenceCounters[$stepId])) {
                    $stepOccurrenceCounters[$stepId] = 0;
                }
                $stepOccurrenceCounters[$stepId]++;
            }

            $pathKey = implode(' => ', $steps);
            if (!isset($pathCounters[$pathKey])) {
                $pathCounters[$pathKey] = ['steps' => $steps, 'visits' => 0];
            }
            $pathCounters[$pathKey]['visits']++;

            $stepCount = count($steps);
            for ($i = 0; $i < $stepCount - 1; $i++) {
                $source = $steps[$i];
                $target = $steps[$i + 1];
                $transitionKey = $source . "\t" . $target;

                if (!isset($transitionCounters[$transitionKey])) {
                    $transitionCounters[$transitionKey] = [
                        'source' => $source,
                        'target' => $target,
                        'visits' => 0,
                    ];
                }

                $transitionCounters[$transitionKey]['visits']++;

                if (!isset($sourceStepCounters[$source])) {
                    $sourceStepCounters[$source] = 0;
                }
                $sourceStepCounters[$source]++;
            }

            $lastStep = $steps[$stepCount - 1];
            if (!isset($dropoffCounters[$lastStep])) {
                $dropoffCounters[$lastStep] = 0;
            }
            $dropoffCounters[$lastStep]++;
        }

        $totalVisits = count($visitSteps);

        usort($pathCounters, static fn (array $a, array $b): int => $b['visits'] <=> $a['visits']);

        $paths = [];
        foreach (array_slice($pathCounters, 0, $limit) as $pathData) {
            $share = $totalVisits > 0 ? $pathData['visits'] / $totalVisits : 0.0;

            $path = new Path(
                $pathData['steps'],
                (int) $pathData['visits'],
                $share,
                count($pathData['steps'])
            );
            $paths[] = $path->toArray();
        }

        usort($transitionCounters, static fn (array $a, array $b): int => $b['visits'] <=> $a['visits']);

        $transitions = [];
        foreach (array_slice($transitionCounters, 0, $limit) as $transitionData) {
            $sourceVisits = $sourceStepCounters[$transitionData['source']] ?? 0;
            $rate = $sourceVisits > 0 ? $transitionData['visits'] / $sourceVisits : 0.0;

            $transition = new Transition(
                $transitionData['source'],
                $transitionData['target'],
                (int) $transitionData['visits'],
                $rate
            );
            $transitions[] = $transition->toArray();
        }

        $dropoffRows = [];
        foreach ($dropoffCounters as $stepId => $dropoffCount) {
            $stepVisits = $stepOccurrenceCounters[$stepId] ?? 0;
            $dropoffRate = $stepVisits > 0 ? $dropoffCount / $stepVisits : 0.0;

            $dropoff = new Dropoff((string) $stepId, (int) $dropoffCount, $dropoffRate);
            $dropoffRows[] = $dropoff->toArray();
        }

        usort($dropoffRows, static fn (array $a, array $b): int => $b['dropoffCount'] <=> $a['dropoffCount']);
        $dropoffs = array_slice($dropoffRows, 0, $limit);

        return [
            'totalVisits' => $totalVisits,
            'paths' => $paths,
            'transitions' => $transitions,
            'dropoffs' => $dropoffs,
        ];
    }
}
