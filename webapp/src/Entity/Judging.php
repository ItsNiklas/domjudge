<?php declare(strict_types=1);
namespace App\Entity;

use App\Utils\Utils;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Result of judging a submission.
 *
 * @ORM\Entity()
 * @ORM\Table(
 *     name="judging",
 *     options={"collate"="utf8mb4_unicode_ci", "charset"="utf8mb4", "comment"="Result of judging a submission"},
 *     indexes={
 *         @ORM\Index(name="submitid", columns={"submitid"}),
 *         @ORM\Index(name="judgehost", columns={"judgehost"}),
 *         @ORM\Index(name="cid", columns={"cid"}),
 *         @ORM\Index(name="rejudgingid", columns={"rejudgingid"}),
 *         @ORM\Index(name="prevjudgingid", columns={"prevjudgingid"})
 *     })
 */
class Judging extends BaseApiEntity implements ExternalRelationshipEntityInterface
{
    const RESULT_CORRECT = 'correct';
    const RESULT_COMPILER_ERROR = 'compiler-error';

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", name="judgingid", length=4,
     *     options={"comment"="Judging ID","unsigned"=true}, nullable=false)
     * @Serializer\SerializedName("id")
     * @Serializer\Type("string")
     */
    protected $judgingid;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="starttime",
     *     options={"comment"="Time judging started", "unsigned"=true},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $starttime;

    /**
     * @var double
     * @ORM\Column(type="decimal", precision=32, scale=9, name="endtime",
     *     options={"comment"="Time judging ended, null = still busy",
     *              "unsigned"=true},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $endtime;

    /**
     * @var string
     * @ORM\Column(type="string", name="result", length=32,
     *     options={"comment"="Result string as defined in config.php"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $result;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="verified",
     *     options={"comment"="Result verified by jury member?",
     *              "default"="0"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $verified = false;

    /**
     * @var string
     * @ORM\Column(type="string", name="jury_member", length=255,
     *     options={"comment"="Name of jury member who verified this"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $jury_member;

    /**
     * @var string
     * @ORM\Column(type="string", name="verify_comment", length=255,
     *     options={"comment"="Optional additional information provided by the verifier"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $verify_comment;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="valid",
     *     options={"comment"="Old judging is marked as invalid when rejudging",
     *              "default"="1"},
     *     nullable=false)
     */
    private $valid = true;

    /**
     * @var resource
     * @ORM\Column(type="blob", name="output_compile",
     *     options={"comment"="Output of the compiling the program"},
     *     nullable=true)
     * @Serializer\Exclude()
     */
    private $output_compile;

    /**
     * @Serializer\Exclude()
     */
    private $output_compile_as_string = null;

    /**
     * @var boolean
     * @ORM\Column(type="boolean", name="seen",
     *     options={"comment"="Whether the team has seen this judging",
     *              "default"="0"},
     *     nullable=false)
     * @Serializer\Exclude()
     */
    private $seen = false;

    /**
     * @ORM\ManyToOne(targetEntity="Contest")
     * @ORM\JoinColumn(name="cid", referencedColumnName="cid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $contest;

    /**
     * @ORM\ManyToOne(targetEntity="Submission", inversedBy="judgings")
     * @ORM\JoinColumn(name="submitid", referencedColumnName="submitid", onDelete="CASCADE")
     * @Serializer\Exclude()
     */
    private $submission;

    /**
     * @ORM\ManyToOne(targetEntity="Judgehost", inversedBy="judgings")
     * @ORM\JoinColumn(name="judgehost", referencedColumnName="hostname")
     * @Serializer\Exclude()
     */
    private $judgehost;

    /**
     * rejudgings have one parent judging
     * @ORM\ManyToOne(targetEntity="Rejudging", inversedBy="judgings")
     * @ORM\JoinColumn(name="rejudgingid", referencedColumnName="rejudgingid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $rejudging;

    /**
     * Rejudgings have one parent judging.
     * @ORM\ManyToOne(targetEntity="Judging")
     * @ORM\JoinColumn(name="prevjudgingid", referencedColumnName="judgingid", onDelete="SET NULL")
     * @Serializer\Exclude()
     */
    private $original_judging;

    /**
     * @ORM\OneToMany(targetEntity="JudgingRun", mappedBy="judging")
     * @Serializer\Exclude()
     */
    private $runs;

    public function getMaxRuntime(): ?float
    {
        $max = 0;
        foreach ($this->runs as $run) {
            $max = max($run->getRuntime(), $max);
        }
        return $max;
    }

    public function getSumRuntime(): float
    {
        $sum = 0;
        foreach ($this->runs as $run) {
            $sum += $run->getRuntime();
        }
        return $sum;
    }

    public function getJudgingid(): int
    {
        return $this->judgingid;
    }

    /** @param string|float $starttime */
    public function setStarttime($starttime): Judging
    {
        $this->starttime = $starttime;
        return $this;
    }

    /** @return string|float */
    public function getStarttime()
    {
        return $this->starttime;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("start_time")
     * @Serializer\Type("string")
     */
    public function getAbsoluteStartTime(): string
    {
        return Utils::absTime($this->getStarttime());
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("start_contest_time")
     * @Serializer\Type("string")
     */
    public function getRelativeStartTime(): string
    {
        return Utils::relTime($this->getStarttime() - $this->getContest()->getStarttime());
    }

    /** @param string|float $endtime */
    public function setEndtime($endtime): Judging
    {
        $this->endtime = $endtime;
        return $this;
    }

    /** @return string|float */
    public function getEndtime()
    {
        return $this->endtime;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("end_time")
     * @Serializer\Type("string")
     */
    public function getAbsoluteEndTime(): ?string
    {
        return $this->getEndtime() ? Utils::absTime($this->getEndtime()) : null;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("end_contest_time")
     * @Serializer\Type("string")
     */
    public function getRelativeEndTime(): ?string
    {
        return $this->getEndtime() ? Utils::relTime($this->getEndtime() - $this->getContest()->getStarttime()) : null;
    }

    public function setResult(string $result): Judging
    {
        $this->result = $result;
        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setVerified(bool $verified): Judging
    {
        $this->verified = $verified;
        return $this;
    }

    public function getVerified(): bool
    {
        return $this->verified;
    }

    public function setJuryMember(string $juryMember): Judging
    {
        $this->jury_member = $juryMember;
        return $this;
    }

    public function getJuryMember(): ?string
    {
        return $this->jury_member;
    }

    public function setVerifyComment(string $verifyComment): Judging
    {
        $this->verify_comment = $verifyComment;
        return $this;
    }

    public function getVerifyComment(): ?string
    {
        return $this->verify_comment;
    }

    public function setValid(bool $valid): Judging
    {
        $this->valid = $valid;
        return $this;
    }

    public function getValid(): bool
    {
        return $this->valid;
    }

    /**
     * @param resource|string $outputCompile
     */
    public function setOutputCompile($outputCompile): Judging
    {
        $this->output_compile = $outputCompile;
        return $this;
    }

    /**
     * @return resource|string|null
     */
    public function getOutputCompile(bool $asString = false)
    {
        if ($asString && $this->output_compile !== null) {
            if ($this->output_compile_as_string === null) {
                $this->output_compile_as_string = stream_get_contents($this->output_compile);
            }
            return $this->output_compile_as_string;
        }
        return $this->output_compile;
    }

    public function setSeen(bool $seen): Judging
    {
        $this->seen = $seen;
        return $this;
    }

    public function getSeen(): bool
    {
        return $this->seen;
    }

    public function setSubmission(?Submission $submission = null): Judging
    {
        $this->submission = $submission;
        return $this;
    }

    public function getSubmission(): Submission
    {
        return $this->submission;
    }

    /**
     * @Serializer\VirtualProperty
     * @Serializer\SerializedName("submission_id")
     * @Serializer\Type("string")
     */
    public function getSubmissionId(): int
    {
        return $this->getSubmission()->getSubmitid();
    }

    public function setContest(?Contest $contest = null): Judging
    {
        $this->contest = $contest;
        return $this;
    }

    public function getContest(): Contest
    {
        return $this->contest;
    }

    public function setRejudging(?Rejudging $rejudging = null): Judging
    {
        $this->rejudging = $rejudging;
        return $this;
    }

    public function getRejudging(): ?Rejudging
    {
        return $this->rejudging;
    }

    public function setOriginalJudging(?Judging $originalJudging = null): Judging
    {
        $this->original_judging = $originalJudging;
        return $this;
    }

    public function getOriginalJudging(): ?Judging
    {
        return $this->original_judging;
    }

    public function setJudgehost(?Judgehost $judgehost = null): Judging
    {
        $this->judgehost = $judgehost;
        return $this;
    }

    public function getJudgehost(): ?Judgehost
    {
        return $this->judgehost;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Expose(if="context.getAttribute('domjudge_service').checkrole('jury')")
     * @Serializer\SerializedName("judgehost")
     */
    public function getJudgehostName(): ?string
    {
        return $this->getJudgehost() ? $this->getJudgehost()->getHostname() : null;
    }

    public function __construct()
    {
        $this->runs = new ArrayCollection();
    }

    public function addRun(JudgingRun $run): Judging
    {
        $this->runs[] = $run;
        return $this;
    }

    public function removeRun(JudgingRun $run)
    {
        $this->runs->removeElement($run);
    }

    public function getRuns(): Collection
    {
        return $this->runs;
    }

    /**
     * Get the entities to check for external ID's while serializing.
     *
     * This method should return an array with as keys the JSON field names and as values the actual entity
     * objects that the SetExternalIdVisitor should check for applicable external ID's.
     */
    public function getExternalRelationships(): array
    {
        return ['submission_id' => $this->getSubmission()];
    }

    /**
     * Check whether this judging is for an aborted judging.
     */
    public function isAborted(): bool
    {
        // This logic has been copied from putSubmissions().
        return $this->getEndtime() === null && !$this->getValid() &&
            (!$this->getRejudging() || !$this->getRejudging()->getValid());
    }

    /**
     * Check whether this judging is still busy while the final result is already known,
     * e.g. with non-lazy evaluation.
     */
    public function isStillBusy(): bool
    {
        return !empty($this->getResult()) && empty($this->getEndtime()) && !$this->isAborted();
    }
}
