import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import DocumentPreviewModal from '@/Components/Pipelines/DocumentPreviewModal.vue'
import PipelineStickyHeader from '@/Components/Pipelines/PipelineStickyHeader.vue'

describe('pipeline page components', () => {
    it('renders the pipeline header and workflow state', () => {
        const wrapper = mount(PipelineStickyHeader, {
            props: {
                pipeline: {
                    name: 'Harbor Point Demo',
                    pipeline_id: 'PL-DEMO-001',
                    status: 'Site Survey',
                },
            },
        })

        expect(wrapper.text()).toContain('Harbor Point Demo')
        expect(wrapper.text()).toContain('Site Survey')
        expect(wrapper.findAll('.bg-emerald-500').length).toBeGreaterThan(0)

        wrapper.unmount()
    })

    it('emits close from the document preview', async () => {
        const wrapper = mount(DocumentPreviewModal, {
            props: {
                document: {
                    name: 'Survey Photo',
                    url: '/storage/demo/survey-photo.jpg',
                },
            },
            global: {
                stubs: { Teleport: true },
            },
        })

        expect(wrapper.text()).toContain('Survey Photo')
        await wrapper.get('button').trigger('click')
        expect(wrapper.emitted('close')).toHaveLength(1)
    })
})
