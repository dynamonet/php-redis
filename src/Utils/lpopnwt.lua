-- Left POP N elements from a variable number of queues, and throttle them
-- redis.replicate_commands()
local to_pop = tonumber(KEYS[1]) or 1
local throttle_name = KEYS[2]
local poped = 0
local rcall = redis.call
local append = table.insert
local format = string.format
local jobs = {}
local sleep_arr = false

for _, queuename in ipairs(ARGV) do
    while poped < to_pop do
        local job = rcall("LPOP",queuename)
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

if poped > 0 and throttle_name and throttle_name ~= "" then
    local state = rcall("HMGET",throttle_name,"tps","next_spot")
    local tps = tonumber(state[1])
    if not tps then
        return "tps not set on " .. throttle_name
    end
    local next_spot_raw = state[2]
    local tps_sleep = 1 / tps
    local time_array = rcall("TIME")
    local now = tonumber(time_array[1]) + (tonumber(time_array[2]) / 1000000)
    local token_count = poped
    local next_spot = -1
    local sleep = 0

    if next_spot_raw then
        next_spot = tonumber(next_spot_raw)
        if now < next_spot then
            sleep = next_spot - now
        end
    end
    
    next_spot = now + sleep + ( tps_sleep * token_count )
    
    rcall(
        "HSET",
        throttle_name,
        "next_spot",
        format("%.6f", next_spot)
    )

    sleep_arr = {
        format("%.6f", sleep),
        format("%.6f", tps_sleep)
    }
end

return {
    poped,
    jobs,
    sleep_arr
}