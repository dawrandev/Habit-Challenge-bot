import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useMe, useMessages, useSendMessage } from '../lib/api'

export default function ChatDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { t } = useTranslation()
  const { data: me } = useMe()
  const { data: messages } = useMessages(id!)
  const send = useSendMessage(id!)
  const [text, setText] = useState('')
  const endRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  function submit() {
    const val = text.trim()
    if (!val) return
    send.mutate(val)
    setText('')
  }

  return (
    <div className="fixed inset-0 z-50 mx-auto flex max-w-md flex-col bg-bg">
      <header className="flex items-center gap-3 border-b border-line px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-3">
        <button
          type="button"
          onClick={() => navigate(-1)}
          className="grid h-9 w-9 place-items-center rounded-full bg-surface text-muted"
        >
          ←
        </button>
        <span className="font-display text-lg">{t('chat.title')}</span>
      </header>

      <div className="flex-1 space-y-2 overflow-y-auto px-4 py-4">
        {messages?.length === 0 && (
          <p className="mt-8 text-center text-sm text-muted">{t('chat.empty')}</p>
        )}
        {messages?.map((m) => {
          if (m.sender_id === null) {
            return (
              <p key={m.id} className="my-2 text-center text-xs text-muted">
                {m.text}
              </p>
            )
          }
          const mine = me && m.sender_id === me.id
          return (
            <div
              key={m.id}
              className={`flex ${mine ? 'justify-end' : 'justify-start'}`}
            >
              <div
                className={`max-w-[75%] rounded-2xl px-3.5 py-2 text-sm ${
                  mine
                    ? 'rounded-br-md bg-you text-bg'
                    : 'rounded-bl-md bg-surface text-text'
                }`}
              >
                {m.text}
              </div>
            </div>
          )
        })}
        <div ref={endRef} />
      </div>

      <div className="flex items-center gap-2 border-t border-line px-3 py-2 pb-[max(0.5rem,env(safe-area-inset-bottom))]">
        <input
          value={text}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && submit()}
          placeholder={t('chat2.placeholder')}
          className="flex-1 rounded-full border border-line bg-surface px-4 py-2.5 text-sm outline-none focus:border-you"
        />
        <button
          type="button"
          onClick={submit}
          disabled={!text.trim()}
          className="grid h-10 w-10 place-items-center rounded-full bg-you text-bg transition active:scale-90 disabled:opacity-40"
        >
          ➤
        </button>
      </div>
    </div>
  )
}
