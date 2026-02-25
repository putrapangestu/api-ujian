<?php

namespace App\Repositories;

use App\Models\Question;
use Illuminate\Database\Eloquent\Collection;

/**
 * Question Repository Implementation
 *
 * Repository ini mengimplementasikan QuestionRepositoryInterface
 *
 * @package App\Repositories
 * @author Development Team
 * @version 1.0.0
 */
class QuestionRepository implements QuestionRepositoryInterface
{
    /**
     * Find question by ID
     *
     * @param string $id
     * @return Question|null
     */
    public function findById(string $id): ?Question
    {
        return Question::find($id);
    }

    /**
     * Get all questions
     *
     * @return Collection
     */
    public function getAll()
    {
        return Question::all();
    }

    /**
     * Get questions by exam ID
     *
     * @param string $examId
     * @return Collection
     */
    public function getByExamId(string $examId)
    {
        return Question::where('exam_id', $examId)->get();
    }

    /**
     * Get questions by question group ID
     *
     * @param string $questionGroupId
     * @return Collection
     */
    public function getByQuestionGroupId(string $questionGroupId)
    {
        return Question::where('question_group_id', $questionGroupId)->get();
    }

    /**
     * Get questions by category ID
     *
     * @param string $categoryId
     * @return Collection
     */
    public function getByCategoryId(string $categoryId)
    {
        return Question::where('category_id', $categoryId)->get();
    }

    /**
     * Get active questions
     *
     * @return Collection
     */
    public function getActiveQuestions()
    {
        return Question::where('is_active', true)->get();
    }

    /**
     * Create new question
     *
     * @param array $data
     * @return Question
     */
    public function create(array $data): Question
    {
        return Question::create($data);
    }

    /**
     * Update question
     *
     * @param string $id
     * @param array $data
     * @return Question|null
     */
    public function update(string $id, array $data): ?Question
    {
        $question = $this->findById($id);

        if ($question) {
            $question->update($data);
            return $question;
        }

        return null;
    }

    /**
     * Delete question
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        $question = $this->findById($id);

        if ($question) {
            return $question->delete();
        }

        return false;
    }

    /**
     * Get questions with options
     *
     * @param string $questionId
     * @return Question|null
     */
    public function getWithOptions(string $questionId): ?Question
    {
        return Question::with('options')->find($questionId);
    }

    /**
     * Count questions by exam ID
     *
     * @param string $examId
     * @return int
     */
    public function countByExamId(string $examId): int
    {
        return Question::where('exam_id', $examId)->count();
    }

    /**
     * Count questions by question group ID
     *
     * @param string $questionGroupId
     * @return int
     */
    public function countByQuestionGroupId(string $questionGroupId): int
    {
        return Question::where('question_group_id', $questionGroupId)->count();
    }

    /**
     * Get questions with related data
     *
     * @param array $relations
     * @return Collection
     */
    public function getWithRelations(array $relations)
    {
        return Question::with($relations)->get();
    }

    /**
     * Search questions
     *
     * @param string $keyword
     * @return Collection
     */
    public function search(string $keyword)
    {
        return Question::where('question_text', 'like', '%' . $keyword . '%')
            ->orWhere('correct_answer', 'like', '%' . $keyword . '%')
            ->get();
    }
}
