# Skills for the AI agent

This directory holds skills for the AI agent plugin
([tool_aiagent](https://lmscloud.io/products/ai-agent)). A skill is a set of instructions that the agent
loads when a task needs it, together with the web service functions the task uses.

Kahoodle does not need the AI agent, and nothing reads this directory when the AI agent is not installed.
When it is, the agent adds these skills to its list of skills and updates them when Kahoodle is upgraded,
unless an administrator has changed them.

Each file is one skill, named after its short name, in the format the AI agent's skill export writes, so a
file can also be imported by hand.
