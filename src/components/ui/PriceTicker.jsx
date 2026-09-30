import { useEffect, useState } from 'react'
import { ChevronDown } from 'lucide-react'
import { getPriceHistory } from '@/services/priceHistory'

/**
 * PriceTicker — coin price list. BTC is always visible; the other coins
 * slide open below it. Each row: coin left, 30-day sparkline, price + 24h change right.
 * @param {object} prices  { [coinId]: { usd, change24h } } from the prices query
 */

const LEAD = { id: 'bitcoin', symbol: 'BTC', name: 'Bitcoin' }
const OTHERS = [
  { id: 'ethereum', symbol: 'ETH',  name: 'Ethereum' },
  { id: 'solana',   symbol: 'SOL',  name: 'Solana' },
  { id: 'litecoin', symbol: 'LTC',  name: 'Litecoin' },
  { id: 'dogecoin', symbol: 'DOGE', name: 'Dogecoin' },
  { id: 'tron',     symbol: 'TRX',  name: 'Tron' },
]

const SPARK_DAYS = 30

function formatUsd(n) {
  if (n >= 1) {
    return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
  }
  return '$' + n.toLocaleString('en-US', { maximumSignificantDigits: 4 })
}

function formatChange(n) {
  const sign = n >= 0 ? '+' : ''
  return sign + n.toFixed(1) + '%'
}

// Normalised SVG points (viewBox 100×24) for the last SPARK_DAYS daily prices
function sparkPoints(dailyMap) {
  const values = Object.keys(dailyMap ?? {}).sort().slice(-SPARK_DAYS).map((d) => dailyMap[d])
  if (values.length < 2) return null
  const min = Math.min(...values)
  const range = Math.max(...values) - min || 1
  const points = values
    .map((v, i) => `${(i / (values.length - 1)) * 100},${22 - ((v - min) / range) * 20}`)
    .join(' ')
  return { points, up: values[values.length - 1] >= values[0] }
}

function Sparkline({ dailyMap, symbol }) {
  const spark = sparkPoints(dailyMap)
  if (!spark) return <span className="ticker-spark" />
  return (
    <svg className="ticker-spark" viewBox="0 0 100 24" preserveAspectRatio="none" aria-label={`${symbol} ${SPARK_DAYS} days`}>
      <polyline points={spark.points} className={spark.up ? 'ticker-spark-up' : 'ticker-spark-down'} />
    </svg>
  )
}

function CoinRow({ symbol, name, coin, history, children }) {
  return (
    <div className="ticker-row">
      <span className="ticker-coin">
        <span className="ticker-symbol">{symbol}</span>
        <span className="ticker-name">{name}</span>
      </span>
      <Sparkline dailyMap={history} symbol={symbol} />
      {coin ? (
        <span className="ticker-quote">
          <span className="ticker-price">{formatUsd(coin.usd)}</span>
          {coin.change24h != null && (
            <span className={coin.change24h >= 0 ? 'ticker-change-up' : 'ticker-change-down'}>
              {formatChange(coin.change24h)}
            </span>
          )}
        </span>
      ) : (
        <span className="inline-block h-4 w-28 rounded animate-pulse ticker-skeleton" />
      )}
      {children}
    </div>
  )
}

export function PriceTicker({ prices }) {
  const [open, setOpen] = useState(false)
  const [history, setHistory] = useState(null)

  // Shares the 1-hour module cache with HistoryChart, so no extra request
  useEffect(() => {
    getPriceHistory().then(setHistory).catch(() => {})
  }, [])

  return (
    <div className="ticker-wrapper">
      <button
        type="button"
        className="ticker-toggle"
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        aria-controls="ticker-others"
        aria-label={open ? 'Hide other coins' : 'Show other coins'}
      >
        <CoinRow {...LEAD} coin={prices?.[LEAD.id]} history={history?.[LEAD.id]}>
          <span className={`ticker-chevron ${open ? 'ticker-chevron-open' : ''}`}>
            <ChevronDown size={16} />
          </span>
        </CoinRow>
      </button>

      {/* grid-rows 0fr → 1fr animates the height without measuring it */}
      <div id="ticker-others" className={`ticker-collapse ${open ? 'ticker-collapse-open' : ''}`}>
        <div className="overflow-hidden">
          {OTHERS.map(({ id, symbol, name }) => (
            <CoinRow key={id} symbol={symbol} name={name} coin={prices?.[id]} history={history?.[id]}>
              <span className="ticker-chevron-spacer" />
            </CoinRow>
          ))}
        </div>
      </div>
    </div>
  )
}
