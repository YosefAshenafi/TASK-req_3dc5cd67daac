import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AssetCard from '@/components/AssetCard.vue'

const mockAudioAsset = {
  id: 1,
  title: 'Test Audio Track',
  description: 'A test description',
  mime_type: 'audio/mpeg',
  duration: 125,
  play_count: 42,
  tags: ['announcement', 'welcome'],
  recommendation_reason: null,
}

const mockVideoAsset = {
  id: 2,
  title: 'Test Video',
  description: null,
  mime_type: 'video/mp4',
  duration: 300,
  play_count: 10,
  tags: [],
  recommendation_reason: 'Based on your recent activity',
}

describe('AssetCard', () => {
  it('renders the asset title', () => {
    const wrapper = mount(AssetCard, { props: { asset: mockAudioAsset } })
    expect(wrapper.text()).toContain('Test Audio Track')
  })

  it('renders description when present', () => {
    const wrapper = mount(AssetCard, { props: { asset: mockAudioAsset } })
    expect(wrapper.text()).toContain('A test description')
  })

  it('renders formatted duration', () => {
    const wrapper = mount(AssetCard, { props: { asset: mockAudioAsset } })
    expect(wrapper.text()).toContain('2:05')
  })

  it('renders play count', () => {
    const wrapper = mount(AssetCard, { props: { asset: mockAudioAsset } })
    expect(wrapper.text()).toContain('42 plays')
  })

  it('renders tags', () => {
    const wrapper = mount(AssetCard, { props: { asset: mockAudioAsset } })
    expect(wrapper.text()).toContain('announcement')
  })

  it('renders recommendation reason when present', () => {
    const wrapper = mount(AssetCard, { props: { asset: mockVideoAsset } })
    expect(wrapper.text()).toContain('Based on your recent activity')
  })

  it('does not render recommendation reason when absent', () => {
    const wrapper = mount(AssetCard, { props: { asset: mockAudioAsset } })
    expect(wrapper.text()).not.toContain('recommendation_reason')
  })

  it('emits open event when the card is clicked', async () => {
    const wrapper = mount(AssetCard, { props: { asset: mockAudioAsset } })
    await wrapper.trigger('click')
    expect(wrapper.emitted('open')).toBeTruthy()
    expect(wrapper.emitted('open')[0]).toEqual([1])
  })

  it('emits play event from the Play button without opening the card', async () => {
    const wrapper = mount(AssetCard, { props: { asset: mockAudioAsset } })
    await wrapper.find('[data-testid="play-btn"]').trigger('click')
    expect(wrapper.emitted('play')).toBeTruthy()
    expect(wrapper.emitted('play')[0]).toEqual([1])
    // .stop prevents the card's open emit from firing
    expect(wrapper.emitted('open')).toBeFalsy()
  })

  it('emits toggle-favorite from the favorite button with the asset', async () => {
    const wrapper = mount(AssetCard, { props: { asset: mockAudioAsset } })
    await wrapper.find('[data-testid="favorite-btn"]').trigger('click')
    expect(wrapper.emitted('toggle-favorite')).toBeTruthy()
    expect(wrapper.emitted('toggle-favorite')[0][0].id).toBe(1)
    expect(wrapper.emitted('open')).toBeFalsy()
  })

  it('reflects favorited state on the favorite button', () => {
    const unfav = mount(AssetCard, { props: { asset: mockAudioAsset, isFavorite: false } })
    expect(unfav.find('[data-testid="favorite-btn"]').attributes('aria-pressed')).toBe('false')
    expect(unfav.find('[data-testid="favorite-btn"]').attributes('aria-label')).toContain('Add')

    const fav = mount(AssetCard, { props: { asset: mockAudioAsset, isFavorite: true } })
    expect(fav.find('[data-testid="favorite-btn"]').attributes('aria-pressed')).toBe('true')
    expect(fav.find('[data-testid="favorite-btn"]').attributes('aria-label')).toContain('Remove')
  })
})
