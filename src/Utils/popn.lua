-- Left POP N elements from a variable number of queues, and throttle them
-- redis.replicate_commands()
local to_pop = tonumber(KEYS[1]) or 1
local side = KEYS[2]; -- "R" or "L"
local poped = 0
local rcall = redis.call
local append = table.insert
local format = string.format
local jobs = {}
local command = "LPOP"

if side == "r" or side == "R" then
    command = "RPOP"
end

for _, queuename in ipairs(ARGV) do
    while poped < to_pop do
        local job = rcall(command, queuename)
        if job then
            append(jobs, job)
            poped = poped + 1
        else
            break
        end
    end
    if poped >= to_pop then
        break
    end
end

return jobs