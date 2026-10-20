import type { StakeTexts } from './api'

/** Garov matni chegarasi — backend `max:200` bilan bir xil (SPEC §17). */
export const STAKE_MAX = 200

/** Ikkala maydon ham to'ldirilganmi (backend `min:2`). */
export function stakesFilled(v: StakeTexts): boolean {
  return v.prize.trim().length >= 2 && v.penalty.trim().length >= 2
}
