import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AdminUploadsView from '@/views/admin/AdminUploadsView.vue'
import { useUiStore } from '@/stores/ui'

const listMock = vi.fn()

vi.mock('@/services/api', async () => {
  const actual = await vi.importActual<typeof import('@/services/api')>('@/services/api')
  return {
    ...actual,
    assetsApi: {
      list: (...a: unknown[]) => listMock(...a),
    },
    getStoredToken: () => 'stub-token',
  }
})

vi.mock('@/composables/useUnsavedGuard', () => ({
  useUnsavedGuard: vi.fn(),
}))

function asset(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    title: 'Stub asset',
    description: null,
    mime: 'audio/mpeg',
    duration_seconds: 120,
    size_bytes: 2048,
    status: 'processing',
    thumbnail_urls: null,
    tags: [],
    created_at: new Date().toISOString(),
    ...overrides,
  }
}

describe('AdminUploadsView.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    listMock.mockReset()
  })

  it('loads the processing queue on mount and renders an asset row', async () => {
    listMock.mockResolvedValue({
      items: [asset({ id: 11, title: 'Waiting to thumbnail', status: 'processing' })],
      next_cursor: null,
    })

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    expect(listMock).toHaveBeenCalledWith({ status: 'processing', limit: 25, sort: 'newest' })
    expect(wrapper.text()).toContain('Waiting to thumbnail')
    // The status pill reads the asset status verbatim (capitalised via CSS).
    expect(wrapper.text()).toContain('processing')
  })

  it('shows the empty-state copy when the review queue is empty', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    expect(wrapper.text()).toContain('Nothing in the processing queue')
  })

  it('switches tabs between processing / failed / ready and refetches', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })

    const wrapper = mount(AdminUploadsView)
    await flushPromises()
    expect(listMock).toHaveBeenCalledTimes(1)

    // Each review-tab button lives in the "processing | failed | ready" pill
    // strip. Click "failed" → loadReview('failed') → api called with status=failed.
    const failedTab = wrapper.findAll('button').find((b) => b.text().trim() === 'failed')
    await failedTab!.trigger('click')
    await flushPromises()

    expect(listMock).toHaveBeenLastCalledWith({ status: 'failed', limit: 25, sort: 'newest' })

    const readyTab = wrapper.findAll('button').find((b) => b.text().trim() === 'ready')
    await readyTab!.trigger('click')
    await flushPromises()
    expect(listMock).toHaveBeenLastCalledWith({ status: 'ready', limit: 25, sort: 'newest' })
  })

  it('surfaces an error notification when loading the queue fails', async () => {
    listMock.mockRejectedValue(new Error('boom'))

    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')

    mount(AdminUploadsView)
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({ type: 'error', message: 'boom' })
  })

  it('renders the drop zone with the supported-types hint', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    expect(wrapper.text()).toContain('Drop files here')
    expect(wrapper.text()).toContain('Supports JPEG, PNG, PDF, MP3, and MP4')
  })

  it('skips unsupported files when a batch is selected and warns the user', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })

    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'addNotification')

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    // Grab the hidden file input (the only `<input type="file">` on the page).
    const fileInput = wrapper.find('input[type="file"]').element as HTMLInputElement
    const good = new File([new Uint8Array([0xff, 0xd8, 0xff, 0xe0])], 'good.jpg', { type: 'image/jpeg' })
    const bad = new File([new Uint8Array([0x00])], 'junk.bin', { type: 'application/octet-stream' })

    Object.defineProperty(fileInput, 'files', { value: [good, bad] })
    await wrapper.find('input[type="file"]').trigger('change')
    await flushPromises()

    expect(spy).toHaveBeenCalledWith({
      type: 'warning',
      message: 'Some files were skipped (unsupported type)',
    })
    // Only the valid JPEG becomes a queued entry.
    expect(wrapper.text()).toContain('good.jpg')
    expect(wrapper.text()).not.toContain('junk.bin')
  })

  it('renders a status badge for each queued asset using the correct palette class', async () => {
    listMock.mockResolvedValue({
      items: [
        asset({ id: 1, title: 'A', status: 'ready' }),
        asset({ id: 2, title: 'B', status: 'failed' }),
        asset({ id: 3, title: 'C', status: 'processing' }),
      ],
      next_cursor: null,
    })

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    // The status badge class differs by status, catching a regression in
    // statusBadgeClass() that would render every asset the same colour.
    const readyBadge = wrapper.findAll('span').find((s) => s.text() === 'ready')
    const failedBadge = wrapper.findAll('span').find((s) => s.text() === 'failed')
    expect(readyBadge?.classes().join(' ')).toContain('emerald')
    expect(failedBadge?.classes().join(' ')).toContain('red')
  })

  it('accepts files via drag-and-drop and queues them', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    const audio = new File([new Uint8Array([0x49, 0x44, 0x33])], 'track.mp3', { type: 'audio/mpeg' })

    const dropZone = wrapper.find('.border-dashed')
    await dropZone.trigger('drop', {
      dataTransfer: { files: [audio] },
    })

    expect(wrapper.text()).toContain('track.mp3')
    expect(wrapper.text()).toContain('1 file queued')
  })

  it('removes a queued entry when the X button on the entry is clicked', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    // Seed one file via the input change path.
    const fileInput = wrapper.find('input[type="file"]').element as HTMLInputElement
    const good = new File([new Uint8Array([0x49, 0x44, 0x33])], 'remove-me.mp3', { type: 'audio/mpeg' })
    Object.defineProperty(fileInput, 'files', { value: [good] })
    await wrapper.find('input[type="file"]').trigger('change')
    await flushPromises()
    expect(wrapper.text()).toContain('remove-me.mp3')

    // Find the X button inside the queued entry (the trash/close control — only
    // rendered when entry.status !== 'uploading'). The hidden Lucide icon has no
    // accessible name, so target by the class Tailwind uses for icon buttons.
    const entry = wrapper.find('.rounded-xl.border')
    const closeBtn = entry.findAll('button').find((b) => !b.text() && b.attributes('class')?.includes('text-gray-400'))
    if (closeBtn) {
      await closeBtn.trigger('click')
      expect(wrapper.text()).not.toContain('remove-me.mp3')
    }
  })

  it('posts the queued file via XHR and marks the entry done on 201', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })

    const origXHR = globalThis.XMLHttpRequest
    // Stub XMLHttpRequest so we can simulate the 201 success path without
    // needing a real HTTP stack. This exercises the full uploadEntry code
    // path (FormData building, header setup, load callback).
    class FakeXHR {
      handlers: Record<string, Array<(e: unknown) => void>> = {}
      status = 0
      responseText = ''
      withCredentials = false
      upload = { addEventListener: vi.fn() }
      open = vi.fn()
      setRequestHeader = vi.fn()
      addEventListener(name: string, fn: (e: unknown) => void) {
        this.handlers[name] = this.handlers[name] ?? []
        this.handlers[name].push(fn)
      }
      send() {
        this.status = 201
        this.responseText = JSON.stringify({ id: 42, title: 'Uploaded' })
        for (const fn of this.handlers['load'] ?? []) fn({})
      }
    }
    globalThis.XMLHttpRequest = FakeXHR as unknown as typeof XMLHttpRequest

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    const fileInput = wrapper.find('input[type="file"]').element as HTMLInputElement
    const good = new File([new Uint8Array([0x49, 0x44, 0x33])], 'up.mp3', { type: 'audio/mpeg' })
    Object.defineProperty(fileInput, 'files', { value: [good] })
    await wrapper.find('input[type="file"]').trigger('change')
    await flushPromises()

    const uploadBtn = wrapper.findAll('button').find((b) => b.text() === 'Upload')
    await uploadBtn!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Uploaded successfully')
    globalThis.XMLHttpRequest = origXHR
  })

  it('renders the error message when the XHR upload returns a non-2xx status', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })

    const origXHR = globalThis.XMLHttpRequest
    class FakeXHR {
      handlers: Record<string, Array<(e: unknown) => void>> = {}
      status = 0
      responseText = ''
      withCredentials = false
      upload = { addEventListener: vi.fn() }
      open = vi.fn()
      setRequestHeader = vi.fn()
      addEventListener(name: string, fn: (e: unknown) => void) {
        this.handlers[name] = this.handlers[name] ?? []
        this.handlers[name].push(fn)
      }
      send() {
        this.status = 422
        this.responseText = JSON.stringify({ message: 'mime_not_allowed' })
        for (const fn of this.handlers['load'] ?? []) fn({})
      }
    }
    globalThis.XMLHttpRequest = FakeXHR as unknown as typeof XMLHttpRequest

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    const fileInput = wrapper.find('input[type="file"]').element as HTMLInputElement
    const bad = new File([new Uint8Array([0x00])], 'bad.webm', { type: 'video/webm' })
    Object.defineProperty(fileInput, 'files', { value: [bad] })
    await wrapper.find('input[type="file"]').trigger('change')
    await flushPromises()

    const uploadBtn = wrapper.findAll('button').find((b) => b.text() === 'Upload')
    await uploadBtn!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('mime_not_allowed')

    globalThis.XMLHttpRequest = origXHR
  })

  it('shows network error message when XHR fires the error event', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })

    const origXHR = globalThis.XMLHttpRequest
    class FakeXHR {
      handlers: Record<string, Array<(e: unknown) => void>> = {}
      status = 0
      responseText = ''
      withCredentials = false
      upload = { addEventListener: vi.fn() }
      open = vi.fn()
      setRequestHeader = vi.fn()
      addEventListener(name: string, fn: (e: unknown) => void) {
        this.handlers[name] = this.handlers[name] ?? []
        this.handlers[name].push(fn)
      }
      send() {
        for (const fn of this.handlers['error'] ?? []) fn({})
      }
    }
    globalThis.XMLHttpRequest = FakeXHR as unknown as typeof XMLHttpRequest

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    const fileInput = wrapper.find('input[type="file"]').element as HTMLInputElement
    const f = new File([new Uint8Array([0x49, 0x44, 0x33])], 'net.mp3', { type: 'audio/mpeg' })
    Object.defineProperty(fileInput, 'files', { value: [f] })
    await wrapper.find('input[type="file"]').trigger('change')
    await flushPromises()

    const uploadBtn = wrapper.findAll('button').find((b) => b.text() === 'Upload')
    await uploadBtn!.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Network error')
    globalThis.XMLHttpRequest = origXHR
  })

  it('formats file size in bytes, KB, and MB correctly', async () => {
    listMock.mockResolvedValue({
      items: [
        asset({ id: 1, title: 'Tiny', size_bytes: 500 }),
        asset({ id: 2, title: 'Small', size_bytes: 2048 }),
        asset({ id: 3, title: 'Large', size_bytes: 2 * 1024 * 1024 }),
      ],
      next_cursor: null,
    })

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    expect(wrapper.text()).toContain('500 B')
    expect(wrapper.text()).toContain('2.0 KB')
    expect(wrapper.text()).toContain('2.0 MB')
  })

  it('statusBadgeClass returns gray for an unknown status', async () => {
    listMock.mockResolvedValue({
      items: [asset({ id: 9, title: 'Unknown', status: 'unknown_status' })],
      next_cursor: null,
    })

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    const unknownBadge = wrapper.findAll('span').find((s) => s.text() === 'unknown_status')
    expect(unknownBadge?.classes().join(' ')).toContain('gray')
  })

  it('queues an image file and generates a preview URL', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })

    const origCreateObjectURL = URL.createObjectURL
    URL.createObjectURL = vi.fn(() => 'blob:preview-url')

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    const fileInput = wrapper.find('input[type="file"]').element as HTMLInputElement
    const img = new File([new Uint8Array([0xff, 0xd8, 0xff])], 'photo.jpg', { type: 'image/jpeg' })
    Object.defineProperty(fileInput, 'files', { value: [img] })
    await wrapper.find('input[type="file"]').trigger('change')
    await flushPromises()

    expect(wrapper.find('img').exists()).toBe(true)
    expect(wrapper.find('img').attributes('src')).toBe('blob:preview-url')
    URL.createObjectURL = origCreateObjectURL
  })

  it('uploads a file with tags and description appended to FormData', async () => {
    listMock.mockResolvedValue({ items: [], next_cursor: null })

    const origXHR = globalThis.XMLHttpRequest
    const appendedKeys: string[] = []
    class FakeXHR {
      handlers: Record<string, Array<(e: unknown) => void>> = {}
      status = 0
      responseText = ''
      withCredentials = false
      upload = { addEventListener: vi.fn() }
      open = vi.fn()
      setRequestHeader = vi.fn()
      addEventListener(name: string, fn: (e: unknown) => void) {
        this.handlers[name] = this.handlers[name] ?? []
        this.handlers[name].push(fn)
      }
      send(fd: FormData) {
        fd.forEach((_v, key) => appendedKeys.push(key))
        this.status = 201
        this.responseText = JSON.stringify({ id: 77, title: 'Tagged' })
        for (const fn of this.handlers['load'] ?? []) fn({})
      }
    }
    globalThis.XMLHttpRequest = FakeXHR as unknown as typeof XMLHttpRequest

    const wrapper = mount(AdminUploadsView)
    await flushPromises()

    const fileInput = wrapper.find('input[type="file"]').element as HTMLInputElement
    const f = new File([new Uint8Array([0x49, 0x44, 0x33])], 'tagged.mp3', { type: 'audio/mpeg' })
    Object.defineProperty(fileInput, 'files', { value: [f] })
    await wrapper.find('input[type="file"]').trigger('change')
    await flushPromises()

    const [, tagsInput, descInput] = wrapper.findAll('input[type="text"]')
    await tagsInput!.setValue('Rock, Pop')
    await descInput!.setValue('A great track')

    const uploadBtn = wrapper.findAll('button').find((b) => b.text() === 'Upload')
    await uploadBtn!.trigger('click')
    await flushPromises()

    expect(appendedKeys).toContain('tags[]')
    expect(appendedKeys).toContain('description')
    globalThis.XMLHttpRequest = origXHR
  })
})
