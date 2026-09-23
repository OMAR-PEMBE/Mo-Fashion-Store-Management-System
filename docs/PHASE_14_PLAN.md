# Phase 14 — Exchanges implementation plan

Status: implemented. The owner approved any available replacement product and
the same three-day (72-hour) limit as returns. See [completion report](PHASE_14.md).

Completed scope:

- Preserve original sale, returned and replacement lines, costs and audit history.
- Calculate discounted return credit and replacement prices on the server.
- Enforce combined return/exchange quantities and refund/exchange financial limits.
- Complete stock movements and any linked refund atomically under row locks.
- Require administrator approval/completion when a refund difference is due.
- Provide review, completion, cancellation, sale history and responsive screens.
- Verify failure rollback, authorization, retries and competing MySQL operations.

Next implementation phase: Phase 15, operating expenses. Completed-sale
cancellation and payment-provider integration remain separate future work.
