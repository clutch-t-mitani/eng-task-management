export const ISSUE_FILTER_EMPTY_VALUE = '__empty__';

export const ISSUE_STATUS = {
    TODO: 1,
    IN_PROGRESS: 2,
    TESTING: 3,
    DONE: 4,
    ON_HOLD: 5,
};

export const ISSUE_STATUSES = [
    { id: ISSUE_STATUS.TODO, label: '未着手' },
    { id: ISSUE_STATUS.IN_PROGRESS, label: '作業中' },
    { id: ISSUE_STATUS.TESTING, label: 'テスト中' },
    { id: ISSUE_STATUS.DONE, label: '完了' },
    { id: ISSUE_STATUS.ON_HOLD, label: '保留' },
];

export const ISSUE_STATUS_COLORS = {
    [ISSUE_STATUS.TODO]: '#e2e8f0',
    [ISSUE_STATUS.IN_PROGRESS]: '#63b3ed',
    [ISSUE_STATUS.TESTING]: '#f6e05e',
    [ISSUE_STATUS.DONE]: '#68d391',
    [ISSUE_STATUS.ON_HOLD]: '#f6ad55',
};

export const ISSUE_STATUS_BADGE_CLASSES = {
    [ISSUE_STATUS.TODO]: 'badge-todo',
    [ISSUE_STATUS.IN_PROGRESS]: 'badge-wip',
    [ISSUE_STATUS.TESTING]: 'badge-test',
    [ISSUE_STATUS.DONE]: 'badge-done',
    [ISSUE_STATUS.ON_HOLD]: 'badge-hold',
};
