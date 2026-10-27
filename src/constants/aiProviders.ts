import type { AiProvider, AiProviderOption } from '../types'

export const AI_PROVIDERS: AiProviderOption[] = [
	{
		id: 'deepseek',
		label: 'DeepSeek',
		defaultModel: 'deepseek-chat',
		supported: true,
	},
	{
		id: 'siliconflow',
		label: 'SiliconFlow',
		defaultModel: 'Qwen/Qwen3-8B',
		supported: true,
	},
]

export function getProviderConfig(provider: AiProvider): AiProviderOption {
	return AI_PROVIDERS.find((candidate) => candidate.id === provider) ?? AI_PROVIDERS[0]
}
