import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import StatCard from '@/components/StatCard.vue'

describe('StatCard', () => {
  describe('label rendering', () => {
    it('displays the label prop', () => {
      const wrapper = mount(StatCard, { props: { label: 'Total Users', value: 42 } })
      expect(wrapper.text()).toContain('Total Users')
    })
  })

  describe('value rendering', () => {
    it('displays a numeric value', () => {
      const wrapper = mount(StatCard, { props: { label: 'Count', value: 99 } })
      expect(wrapper.text()).toContain('99')
    })

    it('displays a string value', () => {
      const wrapper = mount(StatCard, { props: { label: 'Status', value: 'active' } })
      expect(wrapper.text()).toContain('active')
    })

    it('displays em-dash when value is null', () => {
      const wrapper = mount(StatCard, { props: { label: 'Count', value: null } })
      expect(wrapper.text()).toContain('—')
    })

    it('displays em-dash when value is undefined', () => {
      const wrapper = mount(StatCard, { props: { label: 'Count' } })
      expect(wrapper.text()).toContain('—')
    })

    it('displays zero value (not em-dash)', () => {
      const wrapper = mount(StatCard, { props: { label: 'Errors', value: 0 } })
      expect(wrapper.text()).toContain('0')
    })
  })

  describe('color class computation', () => {
    it('applies blue class by default', () => {
      const wrapper = mount(StatCard, { props: { label: 'X', value: 1 } })
      expect(wrapper.vm.valueClass['text-blue-600']).toBe(true)
    })

    it('applies green class for green color', () => {
      const wrapper = mount(StatCard, { props: { label: 'X', value: 1, color: 'green' } })
      expect(wrapper.vm.valueClass['text-green-600']).toBe(true)
    })

    it('applies yellow class for yellow color', () => {
      const wrapper = mount(StatCard, { props: { label: 'X', value: 1, color: 'yellow' } })
      expect(wrapper.vm.valueClass['text-yellow-600']).toBe(true)
    })

    it('applies red class for red color', () => {
      const wrapper = mount(StatCard, { props: { label: 'X', value: 1, color: 'red' } })
      expect(wrapper.vm.valueClass['text-red-600']).toBe(true)
    })

    it('applies purple class for purple color', () => {
      const wrapper = mount(StatCard, { props: { label: 'X', value: 1, color: 'purple' } })
      expect(wrapper.vm.valueClass['text-purple-600']).toBe(true)
    })

    it('does not apply blue class when color is red', () => {
      const wrapper = mount(StatCard, { props: { label: 'X', value: 1, color: 'red' } })
      expect(wrapper.vm.valueClass['text-blue-600']).toBe(false)
    })

    it('does not apply green class when color is blue', () => {
      const wrapper = mount(StatCard, { props: { label: 'X', value: 1, color: 'blue' } })
      expect(wrapper.vm.valueClass['text-green-600']).toBe(false)
    })

    it('only one color class is true at a time', () => {
      const wrapper = mount(StatCard, { props: { label: 'X', value: 1, color: 'purple' } })
      const classes = wrapper.vm.valueClass
      const trueClasses = Object.entries(classes).filter(([, v]) => v === true)
      expect(trueClasses).toHaveLength(1)
      expect(trueClasses[0][0]).toBe('text-purple-600')
    })
  })

  describe('DOM structure', () => {
    it('has a card container', () => {
      const wrapper = mount(StatCard, { props: { label: 'X', value: 1 } })
      expect(wrapper.find('.card').exists()).toBe(true)
    })
  })
})
