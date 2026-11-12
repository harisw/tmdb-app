const PAGE_HTML = __PAGE_HTML__;

const EXPECTED_DOWN_TIMEOUT_MS = 5000;
const BOOT_GRACE_MINUTES = 15;
const ABANDONABLE_METHODS = new Set(['GET', 'HEAD']);
const MINUTES_PER_DAY = 1440;
const MINUTES_PER_WEEK = 7 * MINUTES_PER_DAY;

function toMinutes(time) {
  const [hours, minutes] = time.split(':').map(Number);
  return hours * 60 + minutes;
}

function weeklyEvents(schedule) {
  return ['start', 'stop'].flatMap((type) =>
    schedule[type].days.map((day) => ({ type, at: day * MINUTES_PER_DAY + toMinutes(schedule[type].time) })),
  );
}

function scheduleState(schedule, now) {
  const local = new Date(now + schedule.utcOffsetMinutes * 60000);
  const minuteOfWeek = local.getUTCDay() * MINUTES_PER_DAY + local.getUTCHours() * 60 + local.getUTCMinutes();
  const msIntoMinute = local.getUTCSeconds() * 1000 + local.getUTCMilliseconds();
  const events = weeklyEvents(schedule);

  const minutesSince = (event) => (minuteOfWeek - event.at + MINUTES_PER_WEEK) % MINUTES_PER_WEEK;
  const minutesUntil = (event) => (event.at - minuteOfWeek + MINUTES_PER_WEEK) % MINUTES_PER_WEEK || MINUTES_PER_WEEK;

  const lastEvent = events.reduce((latest, event) => (minutesSince(event) < minutesSince(latest) ? event : latest));
  const isUp = lastEvent.type === 'start';
  const isBooting = isUp && minutesSince(lastEvent) < BOOT_GRACE_MINUTES;
  const minutesToOpen = isBooting
    ? -minutesSince(lastEvent)
    : Math.min(...events.filter((event) => event.type === 'start').map(minutesUntil));

  return {
    expectsUp: isUp && !isBooting,
    openAt: new Date(now + minutesToOpen * 60000 - msIntoMinute),
  };
}

function maintenancePage(request, env, mode, nextOpenAt) {
  const config = {
    siteName: env.SITE_NAME,
    message: env.MESSAGE || null,
    mode,
    nextOpenAt: nextOpenAt ? nextOpenAt.toISOString() : null,
  };
  const script = '<script>window.__MAINTENANCE__ = ' + JSON.stringify(config).replace(/</g, '\\u003c') + ';</script>';
  const retryAfter = nextOpenAt ? Math.max(60, Math.ceil((nextOpenAt - Date.now()) / 1000)) : 60;

  return new Response(request.method === 'HEAD' ? null : PAGE_HTML.replace('</head>', () => script + '</head>'), {
    status: 503,
    headers: {
      'content-type': 'text/html; charset=utf-8',
      'cache-control': 'no-store',
      'retry-after': String(retryAfter),
    },
  });
}

async function fetchWithHeaderTimeout(request, timeoutMs) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    return await fetch(request, { signal: controller.signal });
  } finally {
    clearTimeout(timer);
  }
}

export default {
  async fetch(request, env) {
    const { expectsUp, openAt } = scheduleState(env.SCHEDULE, Date.now());
    const fallback = () =>
      expectsUp ? maintenancePage(request, env, 'unexpected', null) : maintenancePage(request, env, 'scheduled', openAt);
    const canAbandon = !expectsUp && ABANDONABLE_METHODS.has(request.method);

    try {
      const response = canAbandon ? await fetchWithHeaderTimeout(request, EXPECTED_DOWN_TIMEOUT_MS) : await fetch(request);
      return response.status >= 500 ? fallback() : response;
    } catch {
      return fallback();
    }
  },
};
