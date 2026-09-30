import { BLOCKCHAIN_API } from './blockchainApi'

const TTL = 60 * 60 * 1000 // 1 hour
const PARTIAL_TTL = 2 * 60 * 1000 // backend still filling its cache (rate limit) — ask again soon
const COIN_COUNT = 6
let ttl = TTL
let cached = null
let cachedAt = 0

export async function getPriceHistory() {
  if (cached && Date.now() - cachedAt < ttl) return cached
  const key = import.meta.env.VITE_APP_KEY
  const res = await fetch(BLOCKCHAIN_API.priceHistory, {
    headers: key ? { 'X-App-Key': key } : {},
  })
  if (!res.ok) throw new Error('Failed to fetch price history')
  cached = await res.json()
  cachedAt = Date.now()
  ttl = Object.keys(cached).length < COIN_COUNT ? PARTIAL_TTL : TTL
  return cached
}
