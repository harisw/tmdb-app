const PAGE_HTML = __PAGE_HTML__;

const ORIGIN_ERRORS = new Set([520, 521, 522, 523, 524, 525, 526, 530]);
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
  const minutesToStart = Math.min(...events.filter((event) => event.type === 'start').map(minutesUntil));

  return {
    isUp: lastEvent.type === 'start',
    nextOpenAt: new Date(now + minutesToStart * 60000 - msIntoMinute),
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

  return new Response(request.method === 'HEAD' ? null : PAGE_HTML.replace('</head>', script + '</head>'), {
    status: 503,
    headers: {
      'content-type': 'text/html; charset=utf-8',
      'cache-control': 'no-store',
      'retry-after': String(retryAfter),
    },
  });
}

export default {
  async fetch(request, env) {
    const { isUp, nextOpenAt } = scheduleState(env.SCHEDULE, Date.now());

    if (!isUp) {
      return maintenancePage(request, env, 'scheduled', nextOpenAt);
    }

    try {
      const response = await fetch(request);
      return ORIGIN_ERRORS.has(response.status) ? maintenancePage(request, env, 'unexpected', null) : response;
    } catch {
      return maintenancePage(request, env, 'unexpected', null);
    }
  },
};
