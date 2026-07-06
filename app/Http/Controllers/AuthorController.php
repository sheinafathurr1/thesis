<?php

namespace App\Http\Controllers;
use App\Models\Author;

class AuthorController extends Controller
{
    private function getAuthor($id) { return Author::with('affiliation')->findOrFail($id); }

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
}
