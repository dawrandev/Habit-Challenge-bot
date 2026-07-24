import { motion } from 'motion/react'
import type { ReactNode } from 'react'

/** Har sahifa uchun yagona kirish/chiqish animatsiyasi */
export default function PageTransition({ children }: { children: ReactNode }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -8 }}
      transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
      className="flex min-h-full flex-col"
    >
      {children}
    </motion.div>
  )
}
