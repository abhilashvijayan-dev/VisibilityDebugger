/**
 * Copyright © Company. All rights reserved.
 * Mock GraphQL execution for Milestone 3.
 */
define([], function () {
    'use strict';

    return function runMockQuery(payload) {
        return {
            data: {
                mockExecute: true,
                summary: {
                    queryLength: (payload.query || '').length,
                    variableKeys: Object.keys(payload.variables || {}),
                    headerKeys: Object.keys(payload.headers || {})
                }
            },
            meta: {
                mode: 'mock',
                executedAt: new Date().toISOString()
            }
        };
    };
});
