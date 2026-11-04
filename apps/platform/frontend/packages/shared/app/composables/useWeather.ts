export interface HourForecastDto {
  /** HH:MM, wall-clock time at the household. */
  time: string
  temperature: number
  code: number
  label: string
  symbol: string
  /** Chance of precipitation in percent. */
  rain: number | null
}

export interface DayForecastDto {
  /** YYYY-MM-DD in the household's zone. */
  date: string
  min: number
  max: number
  code: number
  label: string
  symbol: string
  /** Hours of sunshine. */
  sunshine?: number | null
  rain?: number | null
  /** For today only the hours still to come. */
  hours?: HourForecastDto[]
}

export interface WeatherDto {
  current: number
  min: number
  max: number
  code: number
  label: string
  symbol: string
  /** `C` or `F`, chosen by the household's place. */
  unit?: string
  today?: DayForecastDto | null
  /** The next days, today not included. Missing from an old cached answer. */
  days?: DayForecastDto[]
}

export function useWeather() {
  const weather = useState<WeatherDto | null>('weather', () => null)
  const hint = useState<string | null>('weather-hint', () => null)

  /** The day shown in the weather window; null while it is closed. */
  const openDay = useState<string | null>('weather-open', () => null)

  async function load(): Promise<void> {
    try {
      // baseURL '/': Nuxt would otherwise prepend app.baseURL to the path.
      const data = await $fetch<{ weather: WeatherDto | null, hint: string | null }>('/api/wall/weather', {
        baseURL: '/',
        credentials: 'same-origin',
      })

      weather.value = data.weather
      hint.value = data.hint
    }
    catch {
      // The server caches for an hour; a hiccup here is no reason
      // to throw away the last known temperature.
    }
  }

  /** Today and the coming days, as the window's tabs. */
  const allDays = computed((): DayForecastDto[] => [
    ...(weather.value?.today ? [weather.value.today] : []),
    ...(weather.value?.days ?? []),
  ])

  /** Opens on a day; without one on today. */
  function show(date?: string): void {
    openDay.value = date ?? allDays.value[0]?.date ?? null
  }

  function close(): void {
    openDay.value = null
  }

  return { weather, hint, load, openDay, allDays, show, close }
}
