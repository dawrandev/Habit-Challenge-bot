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
import { getStartParam } from './lib/telegram'

/** Deep-link taklif: start_param "battle_<token>" bo'lsa → /invite/<token> */
function StartParamHandler() {
  const navigate = useNavigate()
  useEffect(() => {
    const p = getStartParam()
    if (p && p.startsWith('battle_')) {
      const token = p.slice('battle_'.length)
      if (token) navigate(`/invite/${token}`, { replace: true })
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
            <Route path="/battle/:id" element={<BattlePage />} />
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
        <Route
          path="/battle/:id/proof/:challengeId"
          element={<ProofCapturePage />}
        />
        <Route path="/battle/:id/chat" element={<ChatDetailPage />} />
        {/* Tabli qism */}
        <Route path="/*" element={<TabbedApp />} />
      </Routes>
    </BrowserRouter>
  )
}
