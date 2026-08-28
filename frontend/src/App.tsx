import { useEffect } from 'react'
import {
  BrowserRouter,
  Routes,
  Route,
  Navigate,
  useLocation,
  useNavigate,
} from 'react-router-dom'
import { AnimatePresence } from 'motion/react'
import BottomNav from './components/BottomNav'
import HomePage from './pages/HomePage'
import BattlePage from './pages/BattlePage'
import ActivityPage from './pages/ActivityPage'
import ChatPage from './pages/ChatPage'
import ProfilePage from './pages/ProfilePage'
import ProofCapturePage from './pages/ProofCapturePage'
import NewBattlePage from './pages/NewBattlePage'
import ChatDetailPage from './pages/ChatDetailPage'
import AcceptInvitePage from './pages/AcceptInvitePage'
import AboutPage from './pages/AboutPage'
import QuestsPage from './pages/QuestsPage'
import QuestPage from './pages/QuestPage'
import NewQuestPage from './pages/NewQuestPage'
import AcceptQuestPage from './pages/AcceptQuestPage'
import { getStartParam } from './lib/telegram'

/**
 * Deep-link taklif:
 *   battle_<token> → duel taklifi (ikkalangiz bajarasiz)
 *   quest_<token>  → guvohlik taklifi (u bajaradi, sen tekshirasan)
 */
function StartParamHandler() {
  const navigate = useNavigate()
  useEffect(() => {
    const p = getStartParam()
    if (!p) return

    if (p.startsWith('battle_')) {
      const token = p.slice('battle_'.length)
      if (token) navigate(`/invite/${token}`, { replace: true })
      return
    }
    if (p.startsWith('quest_')) {
      const token = p.slice('quest_'.length)
      if (token) navigate(`/quest-invite/${token}`, { replace: true })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])
  return null
}

function TabbedApp() {
  const location = useLocation()
  return (
    <div className="mx-auto flex min-h-dvh max-w-md flex-col bg-bg">
      <main className="flex flex-1 flex-col overflow-x-hidden">
        <AnimatePresence mode="wait">
          <Routes location={location} key={location.pathname}>
            <Route path="/" element={<HomePage />} />
            <Route path="/new" element={<NewBattlePage />} />
            <Route path="/about" element={<AboutPage />} />
            <Route path="/battle/:id" element={<BattlePage />} />
            <Route path="/quests" element={<QuestsPage />} />
            <Route path="/quest/new" element={<NewQuestPage />} />
            <Route path="/quest/:id" element={<QuestPage />} />
            <Route path="/activity" element={<ActivityPage />} />
            <Route path="/chat" element={<ChatPage />} />
            <Route path="/profile" element={<ProfilePage />} />
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </AnimatePresence>
      </main>
      <BottomNav />
    </div>
  )
}

export default function App() {
  return (
    <BrowserRouter>
      <StartParamHandler />
      <Routes>
        {/* To'liq ekran (navigatsiyasiz) */}
        <Route path="/invite/:token" element={<AcceptInvitePage />} />
        <Route path="/quest-invite/:token" element={<AcceptQuestPage />} />
        <Route
          path="/battle/:id/proof/:challengeId"
          element={<ProofCapturePage />}
        />
        <Route
          path="/quest/:id/proof/:challengeId"
          element={<ProofCapturePage />}
        />
        <Route path="/battle/:id/chat" element={<ChatDetailPage />} />
        <Route path="/quest/:id/chat" element={<ChatDetailPage />} />
        {/* Tabli qism */}
        <Route path="/*" element={<TabbedApp />} />
      </Routes>
    </BrowserRouter>
  )
}
