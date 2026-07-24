import { NavLink } from 'react-router-dom'
import { motion } from 'motion/react'
import { useTranslation } from 'react-i18next'

const tabs = [
  { to: '/', icon: '🏠', key: 'home' },
  { to: '/battle/1', icon: '⚔️', key: 'battle' },
  { to: '/activity', icon: '🔔', key: 'activity' },
  { to: '/chat', icon: '💬', key: 'chat' },
  { to: '/profile', icon: '👤', key: 'profile' },
] as const

export default function BottomNav() {
  const { t } = useTranslation()
  return (
    <nav className="sticky bottom-0 z-10 border-t border-line bg-surface/95 backdrop-blur">
      <div className="mx-auto flex max-w-md items-stretch justify-between px-2 py-2 pb-[max(0.5rem,env(safe-area-inset-bottom))]">
        {tabs.map((tab) => (
          <NavLink
            key={tab.key}
            to={tab.to}
            className="relative flex flex-1 flex-col items-center gap-1 rounded-xl py-1.5 text-[10px]"
          >
            {({ isActive }) => (
              <>
                <motion.span
                  className="text-lg"
                  animate={{ scale: isActive ? 1.18 : 1, y: isActive ? -1 : 0 }}
                  transition={{ type: 'spring', stiffness: 400, damping: 20 }}
                >
                  {tab.icon}
                </motion.span>
                <span className={isActive ? 'text-you' : 'text-muted'}>
                  {t(`nav.${tab.key}`)}
                </span>
                {isActive && (
                  <motion.span
                    layoutId="tab-dot"
                    className="absolute -top-[9px] h-1 w-6 rounded-full bg-you"
                    transition={{ type: 'spring', stiffness: 500, damping: 30 }}
                  />
                )}
              </>
            )}
          </NavLink>
        ))}
      </div>
    </nav>
  )
}
