export function getRoleHome(role: string): string {
  switch (role) {
    case 'admin':
      return '/admin'
    case 'technician':
      return '/devices'
    default:
      return '/search'
  }
}
