# Program recommendation flow

1. The exam endpoint serves and grades only active questions. Submission saves category correct counts and the category question counts for that attempt in one transaction. Later question-bank edits do not change those denominators.
2. After submission, the student follows **View My Program Recommendation**. The page loads a calculated exam-only ranking, or an evidence warning if reliable scoring is impossible.
3. The student rates five subject/activity interests from 1 to 5 and clicks **Generate My AI Guidance**. All five ratings are required and validated on the server.
4. Each active, recommendable program uses its catalog `recommendation_profile`. Technical Aptitude maps to Digital Literacy. Categories with positive weights must have exam evidence; programs with missing evidence are excluded.
5. For each program, exam alignment is the weighted average of category percentages. Interest alignment is the weighted average of `(rating - 1) / 4 * 100`. The combined match is `0.8 * exam alignment + 0.2 * interest alignment`. These are provisional design weights, not a validated prediction model.
6. Gemini receives category evidence, ratings, calculated matches, and approved program descriptions, subjects, and career paths. Names, email addresses, account identifiers, and individual exam answers are excluded. Gemini explains program fit, tradeoffs, and a practical next step. It does not choose scores or overwrite the ranking.
7. The server requests structured JSON and checks completion, program codes, duplicates, missing programs, and explanation fields. The AI is instructed not to guarantee readiness, invent facts, or treat zero performance as a strength. Text is rendered through escaped Vue interpolation. Structural validation cannot guarantee every generated sentence is factually correct.
8. The complete payload and interests are saved on the exam attempt. An evidence/catalog/model/version fingerprint reuses an unchanged successful result. Changed inputs invalidate it. Failed AI requests leave calculated matches available and can be retried. The generation endpoint is authenticated and limited to six requests per minute.

Each program also includes a calculated `match_basis`: the points contributed by the exam and interests, normalized subject weights, saved category evidence and ratings, and each category's contribution to the final score. The highest match names the largest contributions and compares its lead with the next-ranked program, including the largest category differences that favor it. Ties and a single rankable program are explicit. These reasons remain available when Gemini is unavailable; they do not depend on generated prose. Category contributions reflect both performance and program weights, so the largest contributor is not automatically the student's strongest subject. Displayed values are rounded.

Gemini receives this same basis and is instructed to explain the highest ranking through specific evidence and approved subjects/careers, distinguish interest from demonstrated ability, and identify a tradeoff and next step. Matcher version 2 invalidates older cached explanations without changing the scoring formula or resetting exams. Registrar-pass responses continue to remove exact scores, category evidence, generated prose, and the new match basis.

## Interpretation and edge cases

- Scores are alignment indices out of 100, not AI confidence or probabilities of graduation, employment, or admission.
- All-zero, absent, or inconsistent evidence produces no ranked recommendation. No arbitrary BSIT default is used.
- Ties are displayed as joint matches. Only a unique winner is mirrored into the existing student recommendation field.
- Legacy results have no category-count snapshot and cannot safely be reconstructed from the current question bank. They receive an evidence warning. This change does not reset completed exams or invent historical scores.
- Five questions cannot support precise conclusions about a student's long-term suitability. Use a larger balanced assessment and adviser-reviewed weights, then evaluate recommendations against adviser judgments and student outcomes before making accuracy claims.
- This is a hybrid recommendation system: transparent scoring plus actual generative AI explanations. It is not a trained career-outcome prediction model.

## Setup and verification

Use PHP 8.4 (the XAMPP PHP on PATH may be older). Run the additive migration:

```powershell
php artisan migrate --path=database/migrations/2026_09_07_000001_add_recommendation_evidence.php
```

The backend reuses `GEMINI_API_KEY` and `GEMINI_MODEL` from `backend/.env`. Never put the key in frontend environment variables. After changing cached configuration, run `php artisan config:clear`.

```powershell
php vendor/bin/phpunit --bootstrap vendor/autoload.php tests/Unit/ProgramRecommendationTest.php
php vendor/bin/phpunit --bootstrap vendor/autoload.php tests/Unit/RecommendationFlowTest.php
```

`php artisan ai:check-program-guidance` makes a real Gemini request with synthetic scores and the approved catalog. It does not read student records or write recommendations. It uses the configured API quota.

From `frontend`, run `node --test tests/exam.test.mjs` and `npm run build`.

Gemini structured output reference: https://ai.google.dev/gemini-api/docs/generate-content/structured-output
