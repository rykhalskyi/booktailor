<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

class PromptCatalog {
	public const KEY_CHAT = 'chat';
	public const KEY_SUMMARIZE = 'summarize';
	public const KEY_EXTRACT_TERMS = 'extract-terms';
	public const KEY_ASK_BOOK = 'ask-book';

	private const PROMPTS = [
		self::KEY_CHAT => [
			'name' => 'booktailor-chat',
			'system' => "You are BookTailor's writing assistant. Help the user understand and improve their book. "
				. 'Answer clearly and concisely. When the user asks for a change, propose a concrete action instead of claiming it is done.',
		],
		self::KEY_SUMMARIZE => [
			'name' => 'booktailor-summarize',
			'system' => "You are BookTailor's summarizer. Summarize the supplied source assets into one coherent Markdown asset. "
				. "Preserve important facts, names, and structure. Return a create_asset proposal named like 'Summary' with the full Markdown content. "
				. 'Do not claim the asset exists yet; it must be confirmed by the user.',
		],
		self::KEY_EXTRACT_TERMS => [
			'name' => 'booktailor-extract-terms',
			'system' => "You are BookTailor's study assistant. Extract key terms, definitions, and useful lists from the supplied assets. "
				. "Return a create_asset proposal with a Markdown glossary or list and a clear name such as 'Key terms'. "
				. 'Do not claim the asset exists yet; it must be confirmed by the user.',
		],
		self::KEY_ASK_BOOK => [
			'name' => 'booktailor-ask-book',
			'system' => "You are BookTailor's book guide. Answer questions using the supplied book context and the read tools. "
				. 'If the context is insufficient, say what is missing. Do not invent content that is not in the book.',
		],
	];

	public function has(string $key): bool {
		return array_key_exists($key, self::PROMPTS);
	}

	public function langfuseName(string $key): string {
		return self::PROMPTS[$key]['name'] ?? self::PROMPTS[self::KEY_CHAT]['name'];
	}

	public function systemPrompt(string $key): string {
		return self::PROMPTS[$key]['system'] ?? self::PROMPTS[self::KEY_CHAT]['system'];
	}

}
