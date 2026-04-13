import { getSiteTitle } from '@/utils/site-settings'

export default function getPageTitle(pageTitle) {
  const title = getSiteTitle()

  if (pageTitle) {
    return `${pageTitle} - ${title}`
  }
  return `${title}`
}
