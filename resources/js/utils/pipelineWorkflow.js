export const PIPELINE_STEPS = [
    { label: 'Prospect', status: 'Prospect' },
    { label: 'Prospecting', status: 'Prospecting' },
    { label: 'Site Survey Pending', status: 'Site Survey Pending' },
    { label: 'Site Survey', status: 'Site Survey' },
    { label: 'Commercials', status: 'Commercials' },
    { label: 'Approval', status: 'Approval' },
    { label: 'Completed', status: 'Completed' },
]

const TERMINAL_STATUSES = new Set(['RFS', 'Inactive', 'Completed'])

export function currentPipelineStep(status) {
    const index = PIPELINE_STEPS.findIndex(step => step.status === status)

    if (index !== -1) {
        return index
    }

    return ['RFD', 'RFS', 'Completed'].includes(status) ? PIPELINE_STEPS.length : 0
}

export function pipelineStepState(status, index) {
    if (TERMINAL_STATUSES.has(status)) {
        return index < PIPELINE_STEPS.length ? 'done' : 'upcoming'
    }

    const currentStep = currentPipelineStep(status)

    if (index < currentStep) {
        return 'done'
    }

    if (index === currentStep) {
        return 'active'
    }

    return 'upcoming'
}

export function visiblePipelineActions(status, permissions = {}) {
    return {
        startAcquisition: status === 'Prospect' && Boolean(permissions.startAcquisition),
        moveToSiteSurveyPending: status === 'Prospecting' && Boolean(permissions.manageSiteVisit),
        markInactive: Boolean(permissions.markInactive) && !['Prospect', 'Inactive', 'Completed', 'Approval'].includes(status),
        markActive: status === 'RFD' && Boolean(permissions.markActive),
        markCompleted: status === 'RFS' && Boolean(permissions.markCompleted),
        manageCommercials: status === 'Commercials' && Boolean(permissions.handleCommercials),
        decideApproval: status === 'Approval' && Boolean(permissions.decideApproval),
    }
}
