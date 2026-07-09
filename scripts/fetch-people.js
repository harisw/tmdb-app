/**
 * Fetches every page of /person/popular from TMDB, extracts a subset of
 * fields from each item, and merges everything into a single JSON file.
 *
 * Requirements: Node.js 18+ (uses the built-in fetch API).
 *
 * Usage:
 *   TMDB_TOKEN="your_v4_bearer_token" node fetch-tmdb-popular.js
 *
 * Or edit the TOKEN constant below directly.
 *
 * Output:
 *   tmdb-popular-people.json in the same folder, containing an array of
 *   { gender, id, adult, name, popularity, profile_path } objects for
 *   every person across every page.
 */

const TOKEN = process.env.TMDB_TOKEN || "PASTE_YOUR_BEARER_TOKEN_HERE";

const BASE_URL = "https://api.themoviedb.org/3/person/popular";
const FIELDS = ["gender", "id", "adult", "name", "popularity", "profile_path"];
const OUTPUT_FILE = "tmdb-popular-people.json";

async function fetchPage(page) {
  const url = `${BASE_URL}?language=en-US&page=${page}`;
  const res = await fetch(url, {
    headers: {
      Authorization: `Bearer ${TOKEN}`,
      accept: "application/json",
    },
  });

  if (!res.ok) {
    const body = await res.text();
    throw new Error(`Request failed for page ${page}: ${res.status} ${res.statusText}\n${body}`);
  }

  return res.json();
}

function pickFields(item) {
  const picked = {};
  for (const field of FIELDS) {
    picked[field] = item[field] ?? null;
  }
  return picked;
}

async function main() {
  if (!TOKEN || TOKEN === "PASTE_YOUR_BEARER_TOKEN_HERE") {
    console.error("Missing TMDB bearer token. Set TMDB_TOKEN env var or edit the TOKEN constant.");
    process.exit(1);
  }

  console.log("Fetching page 1...");
  const firstPage = await fetchPage(1);
  const totalPages = firstPage.total_pages;
  console.log(`Total pages: ${totalPages} (total results: ${firstPage.total_results})`);

  const allResults = [...firstPage.results.map(pickFields)];

  try {
      for (let page = 2; page <= totalPages; page++) {
      console.log(`Fetching page ${page}/${totalPages}...`);
      const data = await fetchPage(page);
      allResults.push(...data.results.map(pickFields));

      // Small delay to be polite to the API / avoid rate limits.
      await new Promise((resolve) => setTimeout(resolve, 150));
    } 
  } catch (error) {
    console.log('error: ', error);
  }
  const fs = await import("node:fs/promises");
  await fs.writeFile(OUTPUT_FILE, JSON.stringify(allResults, null, 2), "utf-8");

  console.log(`Done. Wrote ${allResults.length} records to ${OUTPUT_FILE}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
