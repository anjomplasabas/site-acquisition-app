import { describe, expect, it } from 'vitest'
import { currentPipelineStep, pipelineStepState, visiblePipelineActions } from '@/utils/pipelineWorkflow.js'

describe('pipeline workflow state', () => {
    it('marks previous, current, and future steps', () => {
        expect(currentPipelineStep('Commercials')).toBe(4)
        expect(pipelineStepState('Commercials', 3)).toBe('done')
        expect(pipelineStepState('Commercials', 4)).toBe('active')
        expect(pipelineStepState('Commercials', 5)).toBe('upcoming')
    })

    it('shows terminal pipelines as complete', () => {
        expect(pipelineStepState('Completed', 0)).toBe('done')
        expect(pipelineStepState('Completed', 6)).toBe('done')
    })

    it('derives actions from both status and permissions', () => {
        expect(visiblePipelineActions('Prospect', { startAcquisition: true })).toMatchObject({
            startAcquisition: true,
            markInactive: false,
        })
        expect(visiblePipelineActions('Commercials', { handleCommercials: true, markInactive: true })).toMatchObject({
            manageCommercials: true,
            markInactive: true,
        })
        expect(visiblePipelineActions('Approval', { decideApproval: false, markInactive: true })).toMatchObject({
            decideApproval: false,
            markInactive: false,
        })
    })
})
