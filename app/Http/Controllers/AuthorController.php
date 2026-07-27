<?php

namespace App\Http\Controllers;
use App\Models\Author;

class AuthorController extends Controller
{
    private function getAuthor($id) { return Author::with('affiliation')->findOrFail($id); }

    public function profile($env, $uniq, $type, $id) {
        $author = $this->getAuthor($id);
        $publications = $author->publications();
        return response()->json([
            'author' => $author,
            'profile_summary' => [
                'total_publications' => $publications->count(),
                'total_citations' => $publications->sum('citation_count'),
                'scopus_document_count' => $author->publications()->whereNotNull('scopus_quartile')->count(),
                'garuda_document_count' => $author->publications()->whereNotNull('sinta_accreditation')->count(),
                'scopus_hindex' => $author->scopus_hindex,
                'sinta_score_author' => $author->sinta_score_author,
            ]
        ]);
    }

    public function scopus($env, $uniq, $type, $id) {
        $author = $this->getAuthor($id);
        return response()->json([
            'author' => $author,
            'scopus_documents' => $author->publications()->whereNotNull('scopus_quartile')->get()
        ]);
    }

    public function garuda($env, $uniq, $type, $id) {
        $author = $this->getAuthor($id);
        return response()->json([
            'author' => $author,
            'garuda_documents' => $author->publications()->whereNotNull('sinta_accreditation')->get()
        ]);
    }

    public function google($env, $uniq, $type, $id) {
        $author = $this->getAuthor($id);
        return response()->json([
            'author' => $author,
            'google_documents' => $author->publications()->get()
        ]);
    }
}
