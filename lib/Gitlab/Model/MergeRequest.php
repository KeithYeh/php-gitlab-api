<?php namespace Gitlab\Model;

use Gitlab\Client;

/**
 * Class MergeRequest
 *
 * @property-read int $id
 * @property-read int $iid
 * @property-read string $target_branch
 * @property-read string $source_branch
 * @property-read int $project_id
 * @property-read string $title
 * @property-read string $description
 * @property-read bool $closed
 * @property-read bool $merged
 * @property-read string $state
 * @property-read int $source_project_id
 * @property-read int $target_project_id
 * @property-read int $upvotes
 * @property-read int $downvotes
 * @property-read array $labels
 * @property-read User $author
 * @property-read User $assignee
 * @property-read Project $project
 * @property-read Milestone $milestone
 * @property-read File[] $files
 */
class MergeRequest extends AbstractModel implements Noteable
{
    /**
     * @var array
     */
    protected static $properties = array(
        'id',
        'iid',
        'target_branch',
        'source_branch',
        'project_id',
        'title',
        'description',
        'closed',
        'merged',
        'author',
        'assignee',
        'project',
        'state',
        'source_project_id',
        'target_project_id',
        'upvotes',
        'downvotes',
        'labels',
        'milestone',
        'files'
    );

    /**
     * @param Client  $client
     * @param Project $project
     * @param array   $data
     * @return MergeRequest
     */
    public static function fromArray(Client $client, Project $project, array $data)
    {
        $mr = new static($project, $data['iid'], $client);

        if (isset($data['author'])) {
            $data['author'] = User::fromArray($client, $data['author']);
        }

        if (isset($data['assignee'])) {
            $data['assignee'] = User::fromArray($client, $data['assignee']);
        }

        if (isset($data['milestone'])) {
            $data['milestone'] = Milestone::fromArray($client, $project, $data['milestone']);
        }

        if (isset($data['files'])) {
            $files = array();
            foreach ($data['files'] as $file) {
                $files[] = File::fromArray($client, $project, $file);
            }

            $data['files'] = $files;
        }

        return $mr->hydrate($data);
    }

    /**
     * @param Project $project
     * @param int $iid
     * @param Client $client
     */
    public function __construct(Project $project, $iid = null, Client $client = null)
    {
        $this->setClient($client);
        $this->setData('project', $project);
        $this->setData('iid', $iid);
    }

    /**
     * @return MergeRequest
     */
    public function show()
    {
        $data = $this->api('mr')->show($this->project->id, $this->iid);

        return static::fromArray($this->getClient(), $this->project, $data);
    }

    /**
     * @param array $params
     * @return MergeRequest
     */
    public function update(array $params)
    {
        $data = $this->api('mr')->update($this->project->id, $this->iid, $params);

        return static::fromArray($this->getClient(), $this->project, $data);
    }

    /**
     * @param string $comment
     * @return MergeRequest
     */
    public function close($comment = null)
    {
        if ($comment) {
            $this->addComment($comment);
        }

        return $this->update(array(
            'state_event' => 'close'
        ));
    }

    /**
     * @return MergeRequest
     */
    public function reopen()
    {
        return $this->update(array(
            'state_event' => 'reopen'
        ));
    }

    /**
     * @return MergeRequest
     */
    public function open()
    {
        return $this->reopen();
    }

    /**
     * @param string $message
     * @return MergeRequest
     */
    public function merge($message = null)
    {
        $data = $this->api('mr')->merge($this->project->id, $this->iid, array(
            'merge_commit_message' => $message
        ));

        return static::fromArray($this->getClient(), $this->project, $data);
    }

    /**
     * @return MergeRequest
     */
    public function merged()
    {
        return $this->update(array(
            'state_event' => 'merge'
        ));
    }

    /**
     * @param string $comment
     * @return Note
     */
    public function addComment($comment)
    {
        $data = $this->api('mr')->addNote($this->project->id, $this->iid, $comment);

        return Note::fromArray($this->getClient(), $this, $data);
    }

    /**
     * @return Note[]
     */
    public function showComments()
    {
        $notes = array();
        $data = $this->api('mr')->showNotes($this->project->id, $this->iid);

        foreach ($data as $note) {
            $notes[] = Note::fromArray($this->getClient(), $this, $note);
        }

        return $notes;
    }

    /**
     * @return bool
     */
    public function isClosed()
    {
        return in_array($this->state, array('closed', 'merged'));
    }

    /**
     * @return MergeRequest
     */
    public function changes()
    {
        $data = $this->api('mr')->changes($this->project->id, $this->id);

        return static::fromArray($this->getClient(), $this->project, $data);
    }

    /**
     * @return MergeRequest
     */
    public function subscribe()
    {
        $data = $this->api('mr')->subscribe($this->project->id, $this->iid);

        // API will return 304 when already subscribed
        return $data ? static::fromArray($this->getClient(), $this->project, $data) : $this;
    }

    /**
     * @return MergeRequest
     */
    public function unsubscribe()
    {
        $data = $this->api('mr')->unsubscribe($this->project->id, $this->iid);

        // API will return 304 when already unsubscribe
        return $data ? static::fromArray($this->getClient(), $this->project, $data) : $this;
    }
}
