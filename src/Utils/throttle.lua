-- redis.replicate_commands()
local rcall = redis.call
local throttle_name = KEYS[1]
local token_count = ARGV[1] or 1
local time_array = rcall("TIME")
local now = tonumber(time_array[1]) + (tonumber(time_array[2]) / 1000000)
local state = rcall("HMGET",throttle_name,"tps","next_spot")
local tps = tonumber(state[1])
local tps_sleep = 1 / tps
local next_spot_raw = state[2]
local next_spot = -1
local sleep = 0
local format = string.format

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

return {
    format("%.6f", sleep),
    format("%.6f", tps_sleep)
}