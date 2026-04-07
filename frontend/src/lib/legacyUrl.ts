export function resolveLegacyUrl(pathOrUrl: string): string {
  if (!pathOrUrl) return pathOrUrl

  const isAbsolute = /^https?:\/\//i.test(pathOrUrl)
  if (isAbsolute) return pathOrUrl

  if (!import.meta.env.DEV) return pathOrUrl

  // In Vite dev mode, legacy PHP endpoints must hit Apache on localhost.
  if (pathOrUrl.startsWith('/')) {
    return `http://localhost${pathOrUrl}`
  }

  return pathOrUrl
}
