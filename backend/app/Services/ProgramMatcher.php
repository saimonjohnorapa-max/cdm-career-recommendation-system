<?php

namespace App\Services;

class ProgramMatcher
{
    public const VERSION = '2';
    public const INTEREST_CATEGORIES = ['General Mathematics', 'Science', 'Reading Comprehension', 'Logical Reasoning', 'Digital Literacy'];

    public function match(array $scores, array $maximums, array $interests, array $courses): array
    {
        $evidence = [];
        foreach ($maximums as $category => $maximum) {
            $correct = $scores[$category] ?? null;
            if (!is_numeric($maximum) || $maximum <= 0 || !is_numeric($correct) || $correct < 0 || $correct > $maximum) {
                return $this->emptyResult('The saved exam evidence is incomplete or inconsistent. Please contact the Registrar.');
            }
            $evidence[$category] = ['correct' => (int) $correct, 'total' => (int) $maximum, 'percentage' => round($correct / $maximum * 100, 2)];
        }
        if (!$evidence || array_sum(array_column($evidence, 'correct')) <= 0) {
            return $this->emptyResult('There is not enough verified exam evidence to rank programs. Please discuss your interests and assessment with a school adviser.');
        }
        $ranked = [];
        foreach ($courses as $course) {
            $exam = $interest = $weights = 0;
            $categoryWeights = [];
            $complete = true;
            foreach ($course['recommendation_profile'] ?? [] as $category => $weight) {
                if (!is_numeric($weight) || $weight <= 0) continue;
                $category = $category === 'Technical Aptitude' ? 'Digital Literacy' : $category;
                if (!isset($evidence[$category])) { $complete = false; break; }
                $exam += $evidence[$category]['percentage'] * $weight;
                $interest += (($interests[$category] ?? 1) - 1) / 4 * 100 * $weight;
                $weights += $weight;
                $categoryWeights[$category] = ($categoryWeights[$category] ?? 0) + $weight;
            }
            if (!$complete || !$weights) continue;
            $exam /= $weights;
            $interest /= $weights;
            $factors = [];
            foreach ($categoryWeights as $category => $weight) {
                $share = $weight / $weights;
                $examPoints = $evidence[$category]['percentage'] * $share * ($interests ? .8 : 1);
                $interestPoints = $interests ? (($interests[$category] ?? 1) - 1) / 4 * 100 * $share * .2 : 0;
                $factors[] = [
                    'category' => $category,
                    'weight' => round($share * 100, 2),
                    'exam_correct' => $evidence[$category]['correct'],
                    'exam_total' => $evidence[$category]['total'],
                    'interest_rating' => $interests[$category] ?? null,
                    'points' => round($examPoints + $interestPoints, 2),
                ];
            }
            usort($factors, fn ($a, $b) => ($b['points'] <=> $a['points']) ?: strcmp($a['category'], $b['category']));
            $leadingCategories = array_column(array_slice(array_values(array_filter($factors, fn ($factor) => $factor['points'] > 0)), 0, 2), 'category');
            $ranked[] = ['course_id' => $course['id'], 'course_code' => $course['code'], 'course_name' => $course['name'],
                'score' => round($interests ? $exam * .8 + $interest * .2 : $exam, 2),
                'exam_match' => round($exam, 2), 'interest_match' => $interests ? round($interest, 2) : null,
                'match_basis' => [
                    'summary' => $leadingCategories
                        ? 'Your largest weighted contributions to this match come from '.implode(' and ', $leadingCategories).'.'
                        : 'The available assessment and interest evidence contributes no match points for this program.',
                    'exam_points' => round($exam * ($interests ? .8 : 1), 2),
                    'interest_points' => $interests ? round($interest * .2, 2) : null,
                    'factors' => $factors,
                    'comparison' => null,
                ]];
        }
        usort($ranked, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['course_code'], $b['course_code']));
        if (!$ranked || $ranked[0]['score'] <= 0) return $this->emptyResult('No active program has sufficient matching evidence. Please contact a school adviser.');
        $topCodes = array_column(array_values(array_filter($ranked, fn ($p) => $p['score'] === $ranked[0]['score'])), 'course_code');
        foreach ($ranked as &$program) {
            if (!in_array($program['course_code'], $topCodes, true)) continue;
            $program['match_basis']['summary'] = (count($topCodes) > 1
                ? 'This program shares the highest calculated match; the evidence does not identify a single strongest program. '
                : (count($ranked) > 1
                    ? 'This program has the highest combined alignment with your evidence among the programs compared. '
                    : 'This is the only program with enough matching evidence to be ranked. '))
                .$program['match_basis']['summary'];
        }
        unset($program);
        if (count($topCodes) === 1 && count($ranked) > 1) {
            $runnerUp = $ranked[1];
            $runnerPoints = array_column($runnerUp['match_basis']['factors'], 'points', 'category');
            $advantages = [];
            foreach ($ranked[0]['match_basis']['factors'] as $factor) {
                $difference = round($factor['points'] - ($runnerPoints[$factor['category']] ?? 0), 2);
                if ($difference > 0) $advantages[] = ['category' => $factor['category'], 'points' => $difference];
            }
            usort($advantages, fn ($a, $b) => ($b['points'] <=> $a['points']) ?: strcmp($a['category'], $b['category']));
            $ranked[0]['match_basis']['comparison'] = [
                'course_code' => $runnerUp['course_code'], 'course_name' => $runnerUp['course_name'],
                'score_gap' => round($ranked[0]['score'] - $runnerUp['score'], 2),
                'advantages' => array_slice($advantages, 0, 2),
            ];
        }
        return ['status' => 'ready', 'ranked_programs' => $ranked, 'evidence' => $evidence,
            'tied_top_codes' => $topCodes,
            'method' => $interests ? '80% exam alignment + 20% stated interests' : 'Exam alignment only',
            'limitation' => 'Preliminary guidance, not admission eligibility or a probability of success. Short assessments provide limited evidence. The weighting has not been validated against student outcomes.'];
    }

    private function emptyResult(string $message): array
    {
        return ['status' => 'insufficient_evidence', 'message' => $message, 'ranked_programs' => [], 'evidence' => []];
    }
}
