-- Left POP N elements from a variable number of queues, and throttle them
-- redis.replicate_commands()
local to_pop = tonumber(KEYS[1]) or 1
local poped = 0
local rcall = redis.call
local append = table.insert
local format = string.format
local result = {}

for i, queuename in ipairs(ARGV) do
    local jobs = {}
    while poped < to_pop do
        local job = rcall("LPOP", queuename)
        if job then
            append(jobs, job)
            poped = poped + 1
        else
            break
        end
    end
    result[i] = jobs
end

return result