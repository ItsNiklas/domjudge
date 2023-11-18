<?php

declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Service\DOMJudgeService;
use App\Entity\Contest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

#[Route(path: '/team')]
class CourseController extends BaseController
{
    const TOTAL_QUESTIONS = 30; // Assuming these are your constants
    const MIN_CORRECT_TO_PASS = 18;
    const MIN_WEEKS_TO_PASS = 5;
    const PASS_PERCENTAGE = 60;

    public function __construct(
        protected readonly DOMJudgeService $dj,
        protected readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(path: '/course', name: 'team_course')]
    public function courseAction(): Response
    {
        $sql = "SELECT team.name,
        team.teamid,
        SUM(filtered_scorecache.is_correct_public)                                                  as total_correct,
        SUM(IF(filtered_scorecache.shortname = 'week01', filtered_scorecache.is_correct_public,
               0))                                                                                  as total_correct_week_1,
        SUM(IF(filtered_scorecache.shortname = 'week02', filtered_scorecache.is_correct_public,
               0))                                                                                  as total_correct_week_2,
        SUM(IF(filtered_scorecache.shortname = 'week03', filtered_scorecache.is_correct_public,
               0))                                                                                  as total_correct_week_3,
        SUM(IF(filtered_scorecache.shortname = 'week04', filtered_scorecache.is_correct_public,
               0))                                                                                  as total_correct_week_4,
        SUM(IF(filtered_scorecache.shortname = 'week05', filtered_scorecache.is_correct_public,
               0))                                                                                  as total_correct_week_5,
        SUM(IF(filtered_scorecache.shortname = 'week06', filtered_scorecache.is_correct_public,
               0))                                                                                  as total_correct_week_6
            FROM (SELECT teamid, shortname, is_correct_public, runtime_public
                FROM scorecache
                            INNER JOIN contest ON scorecache.cid = contest.cid
                WHERE shortname LIKE 'week%') AS filtered_scorecache
                    INNER JOIN team ON filtered_scorecache.teamid = team.teamid
                    INNER JOIN user ON user.teamid = team.teamid
            WHERE team.categoryid = 3          -- students
            AND user.first_login IS NOT NULL -- no ghosts
            -- AND (team.externalid IS NULL OR team.externalid != 'team_niklas.bauer01')
            GROUP BY team.name
            ORDER BY total_correct DESC, SUM(filtered_scorecache.runtime_public), team.teamid;
        ";

        $connection = $this->em->getConnection();
        $results = $connection->prepare($sql)->executeQuery()->fetchAllAssociative();

        $maxCorrect = !empty($results) ? max(array_column($results, 'total_correct')) : 0;
        $data = [];

        foreach ($results as $index => $result) {
            $teamName = $result['name'];
            $totalCorrect = (int) $result['total_correct'];

            // Calculate weeks with at least 2 correct answers across all CIDs
            $weeks = 0;
            for ($week = 1; $week <= 6; $week++) {
                if ((int) $result["total_correct_week_$week"] >= 2) {
                    $weeks++;
                }
            }

            // Determine if the team passed
            $passed = $totalCorrect >= self::MIN_CORRECT_TO_PASS && $weeks >= self::MIN_WEEKS_TO_PASS ? 'Yes' : 'No';

            // Determine if the team failed
            $weeksToGo = 6 - ceil($maxCorrect / 5) + 1;
            $maxPossibleCorrect = $totalCorrect + $weeksToGo * 5;
            $maxPossibleWeeks = $weeks + $weeksToGo;
            $failed = $maxPossibleCorrect < self::MIN_CORRECT_TO_PASS || $maxPossibleWeeks < self::MIN_WEEKS_TO_PASS ? 'Yes' : 'No';

            $data[$teamName] = [
                'correct' => $totalCorrect,
                'teamid' => $result['teamid'],
                'weeks' => $weeks,
                'passed' => $passed,
                'failed' => $failed,
                'index' => $index,
                'teamName' => $teamName,
            ];
        }

        usort($data, function ($a, $b) {
            if ($a['weeks'] == $b['weeks']) {
                return $a['index'] <=> $b['index']; // Maintain original order if weeks are the same
            }
            return $b['weeks'] <=> $a['weeks']; // Sort by weeks in descending order
        });

        // For the timerange:
        $contests = $this->em->createQueryBuilder()
            ->select('c.cid') // Selecting cid from contest
            ->from(Contest::class, 'c') // Assuming your entity is Contest and aliased as 'c'
            ->where('c.shortname LIKE :prefix') // Where shortname starts with 'week'
            ->setParameter('prefix', 'week%') // Setting the 'week%' parameter
            ->orderBy('c.shortname') // Ordering by shortname
            ->getQuery()
            ->getResult();

        return $this->render('team/course.html.twig', [
            'data' => $data,
            'contestFirst' => $this->dj->getContest($contests[0]['cid']),
            'contestLast' => $this->dj->getContest(end($contests)['cid']),
            'hide_progress_bar' => true, // Hide the progress bar in the base template
            'myTeamId' => $this->dj->getUser()->getTeam()->getTeamid(),
            'maxCorrect' => $maxCorrect,
            'totalQuestions' => self::TOTAL_QUESTIONS,
            'minCorrectToPass' => self::MIN_CORRECT_TO_PASS,
            'minWeeksToPass' => self::MIN_WEEKS_TO_PASS,
            'passPercentage' => self::PASS_PERCENTAGE,
        ]);
    }
}
