export interface AssistantLink {
  label: string
  href: string
}

export interface AssistantQuestion {
  id: string
  category: string
  question: string
}

export interface AssistantKnowledge {
  version: string
  updated_at: string | null
  questions: AssistantQuestion[]
}

export interface AssistantChatResponse {
  matched: boolean
  answer: string
  category: string
  question: string | null
  links: AssistantLink[]
  suggestions: AssistantQuestion[]
}

export interface AssistantContactResponse {
  ok: boolean
  message: string
}

export interface ChatMessage {
  role: 'user' | 'assistant'
  text: string
  links?: AssistantLink[]
  suggestions?: AssistantQuestion[]
}
